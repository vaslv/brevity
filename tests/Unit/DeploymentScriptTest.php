<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class DeploymentScriptTest extends TestCase
{
    private string $directory;

    /** @return array<string, array{string}> */
    public static function failures(): array
    {
        return array_combine(['pull', 'migration', 'create', 'web', 'horizon', 'proxy', 'smoke'], array_map(fn (string $failure): array => [$failure], ['pull', 'migration', 'create', 'web', 'horizon', 'proxy', 'smoke']));
    }

    public function test_deploy_recovers_two_web_containers_left_by_an_interrupted_release(): void
    {
        mkdir($this->directory.'/.deploy');
        file_put_contents($this->directory.'/.deploy/current', "registry/app:old\n".$this->directory."/.env\naaaa\n");
        file_put_contents($this->directory.'/state.json', json_encode(['web' => ['aaaa', 'cccc'], 'routed' => ['cccc']]));

        $process = $this->deploy(['deploy', 'registry/app:new', 'input.env']);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame(['bbbb'], $this->state()['web']);
        $this->assertSame(['bbbb'], $this->state()['routed']);
    }

    public function test_empty_server_requires_an_explicit_rollback_target(): void
    {
        file_put_contents($this->directory.'/state.json', json_encode(['web' => [], 'routed' => []]));

        $process = $this->deploy(['rollback']);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('Set ROLLBACK_TAG', $process->getErrorOutput());
    }

    public function test_explicit_rollback_recovers_an_empty_server_without_release_history(): void
    {
        file_put_contents($this->directory.'/state.json', json_encode(['web' => [], 'routed' => []]));

        $process = $this->deploy(['rollback', 'registry/app:old']);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame(['bbbb'], $this->state()['routed']);
        $this->assertStringStartsWith("registry/app:old\n", file_get_contents($this->directory.'/.deploy/current'));
    }

    #[DataProvider('failures')]
    public function test_failed_deployment_keeps_the_previous_web_and_release(string $failure): void
    {
        $process = $this->deploy(['deploy', 'registry/app:new', 'input.env'], $failure);

        $this->assertNotSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame(['aaaa'], $this->state()['web']);
        $this->assertSame(['aaaa'], $this->state()['routed']);
        $this->assertStringStartsWith("registry/app:old\n", file_get_contents($this->directory.'/.deploy/current'));
        $this->assertFileExists($this->directory.'/.deploy/failed');
    }

    public function test_rollback_after_failed_migration_uses_last_working_release_without_migrating(): void
    {
        $this->deploy(['deploy', 'registry/app:new', 'input.env'], 'migration');
        $process = $this->deploy(['rollback']);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $calls = array_map(fn (string $line): array => json_decode($line, true), file($this->directory.'/calls.jsonl', FILE_IGNORE_NEW_LINES));
        $schedulerStarts = array_values(array_filter($calls, fn (array $call): bool => in_array('--force-recreate', $call['args'], true)));
        $this->assertSame('registry/app:old', $schedulerStarts[1]['image']);
        $this->assertSame('0', $schedulerStarts[1]['migrate']);
        $this->assertSame(['bbbb'], $this->state()['web']);
    }

    public function test_rollback_recovers_using_the_other_web_if_saved_active_is_unhealthy(): void
    {
        mkdir($this->directory.'/.deploy');
        file_put_contents($this->directory.'/.deploy/current', "registry/app:broken\n".$this->directory."/input.env\naaaa\n");
        file_put_contents($this->directory.'/state.json', json_encode(['web' => ['aaaa', 'cccc'], 'routed' => ['aaaa']]));

        $process = $this->deploy(['rollback'], 'active');

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame(['bbbb'], $this->state()['routed']);
        $this->assertStringStartsWith("registry/app:old\n", file_get_contents($this->directory.'/.deploy/current'));
    }

    public function test_rollback_uses_previous_successful_release_and_its_environment(): void
    {
        mkdir($this->directory.'/.deploy');
        file_put_contents($this->directory.'/.deploy/current', "registry/app:new\n".$this->directory.'/input.env');
        file_put_contents($this->directory.'/.deploy/previous', "registry/app:old\n".$this->directory.'/.env');

        $process = $this->deploy(['rollback']);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $release = file($this->directory.'/.deploy/current', FILE_IGNORE_NEW_LINES);
        $this->assertSame('registry/app:old', $release[0]);
        $this->assertSame('APP_HOST=old.example.test', file_get_contents($release[1]));
    }

    public function test_scheduler_does_not_start_scheduled_tasks_after_migration_failure(): void
    {
        $process = $this->scheduler('1');

        $this->assertSame(1, $process->getExitCode());
        $this->assertSame("artisan migrate --force --no-interaction\n", $process->getOutput());
    }

    public function test_scheduler_rollback_skips_migrations_and_domain_sync(): void
    {
        $process = $this->scheduler('0');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringNotContainsString('migrate', $process->getOutput());
        $this->assertStringNotContainsString('domains:sync', $process->getOutput());
        $this->assertStringContainsString('artisan schedule:work', $process->getOutput());
    }

    public function test_successful_deployment_switches_traffic_before_removing_old_web(): void
    {
        $process = $this->deploy(['deploy', 'registry/app:new', 'input.env']);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame(['bbbb'], $this->state()['web']);
        $this->assertSame(['bbbb'], $this->state()['routed']);
        $this->assertStringStartsWith("registry/app:new\n", file_get_contents($this->directory.'/.deploy/current'));
        $this->assertStringStartsWith("registry/app:old\n", file_get_contents($this->directory.'/.deploy/previous'));
        $this->assertSame('APP_HOST=old.example.test', file_get_contents($this->directory.'/.env'));
        $this->assertFileDoesNotExist($this->directory.'/.deploy/failed');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/brevity-deploy-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/bin', 0700, true);
        foreach (['deploy.sh', 'deploy-proxy.sh', 'docker-compose.production.yml'] as $file) {
            copy(dirname(__DIR__, 2).'/'.$file, $this->directory.'/'.$file);
        }
        file_put_contents($this->directory.'/.env', 'APP_HOST=old.example.test');
        file_put_contents($this->directory.'/input.env', 'APP_HOST=new.example.test');
        file_put_contents($this->directory.'/state.json', json_encode(['web' => ['aaaa'], 'routed' => ['aaaa']]));
        file_put_contents($this->directory.'/bin/sleep', "#!/bin/sh\nexit 0\n");
        chmod($this->directory.'/bin/sleep', 0700);
        file_put_contents($this->directory.'/bin/curl', "#!/bin/sh\nprintf '%s' 503\n");
        chmod($this->directory.'/bin/curl', 0700);
        file_put_contents($this->directory.'/bin/docker', <<<'PHP'
#!/usr/bin/env php
<?php
$args = array_slice($argv, 1);
$state = json_decode(file_get_contents('state.json'), true);
$failure = getenv('DEPLOY_TEST_FAILURE');
file_put_contents('calls.jsonl', json_encode(['args' => $args, 'image' => getenv('LARAVEL_IMAGE'), 'migrate' => getenv('RUN_MIGRATIONS')])."\n", FILE_APPEND);
$finish = function (string $output = '', int $code = 0) use (&$state): never {
    file_put_contents('state.json', json_encode($state));
    echo $output;
    exit($code);
};
if ($args[0] === 'ps') { $finish(implode("\n", $state['web'])); }
if ($args[0] === 'pull') { $finish('', $failure === 'pull' ? 1 : 0); }
if ($args[0] === 'logs') { $finish('Simulated deployment error'); }
if ($args[0] === 'inspect') {
    $id = end($args);
    $format = $args[2];
    if ($format === '{{.Id}}') { $finish($id); }
    if ($format === '{{.Config.Image}}') { $finish('registry/app:old'); }
    if ($format === '{{.State.Running}}') { $finish('true'); }
    if (str_contains($format, '.Mounts')) { $finish(getcwd().'/.env'); }
    if (($id === 'scheduler' && $failure === 'migration') || ($id === 'bbbb' && $failure === 'web') || ($id === 'horizon' && $failure === 'horizon')) { $finish('exited unhealthy'); }
    $finish('running healthy');
}
if ($args[0] === 'exec') {
    if ($args[1] === 'aaaa' && $failure === 'active') { $finish('', 1); }
    if ($args[1] === '-i') {
        stream_get_contents(STDIN);
        $draining = end($args);
        if ($draining === 'aaaa' && $failure === 'proxy') { $finish('', 1); }
        $state['routed'] = array_values(array_diff($state['web'], [$draining]));
    }
    $finish();
}
if ($args[0] === 'rm' || $args[0] === 'stop') {
    $id = end($args);
    // Removing a backend while it is still routed is an outage in the fake.
    if (in_array($id, $state['routed'], true)) { $finish('Removed a routed backend', 70); }
    $state['web'] = array_values(array_diff($state['web'], [$id]));
    $finish();
}
if ($args[0] === 'compose') {
    $command = array_slice($args, 5);
    if ($command[0] === 'ps') {
        $service = end($command);
        $finish($service === 'web' ? implode("\n", $state['web']) : $service);
    }
    if ($command[0] === 'up' && end($command) === 'web') {
        if (!in_array('--no-recreate', $command, true) || !in_array('--no-deps', $command, true)) { $finish('Unsafe replacement', 71); }
        $state['web'][] = 'bbbb';
        if ($failure === 'create') { $finish('', 1); }
    }
    $finish();
}
$finish('Unexpected Docker command', 72);
PHP);
        chmod($this->directory.'/bin/docker', 0700);
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->directory]))->mustRun();
        parent::tearDown();
    }

    /** @param array<string> $arguments */
    private function deploy(array $arguments, string $failure = ''): Process
    {
        $process = new Process(['sh', 'deploy.sh', ...$arguments], $this->directory, [
            'PATH' => $this->directory.'/bin:'.getenv('PATH'),
            'DEPLOY_TEST_FAILURE' => $failure,
            'HEALTHCHECK_URL' => $failure === 'smoke' ? 'https://example.test/up' : '',
        ]);
        $process->run();

        return $process;
    }

    private function scheduler(string $migrate): Process
    {
        file_put_contents($this->directory.'/bin/php', <<<'SH'
#!/bin/sh
printf '%s\n' "$*"
if test "$2" = migrate; then exit 1; fi
SH);
        chmod($this->directory.'/bin/php', 0700);
        $config = Yaml::parseFile($this->directory.'/docker-compose.production.yml');
        $command = str_replace(['$$', '/tmp/healthy'], ['$', $this->directory.'/healthy'], $config['services']['scheduler']['command']);
        $process = Process::fromShellCommandline($command, $this->directory, [
            'PATH' => $this->directory.'/bin:'.getenv('PATH'),
            'RUN_MIGRATIONS' => $migrate,
        ]);
        $process->run();

        return $process;
    }

    /** @return array{web: array<string>, routed: array<string>} */
    private function state(): array
    {
        return json_decode(file_get_contents($this->directory.'/state.json'), true);
    }
}
