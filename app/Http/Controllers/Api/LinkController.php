<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Resources\LinkResource;
use App\Models\Condition;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Url;
use Illuminate\Support\Facades\DB;
use League\Uri\Modifier;
use Throwable;

class LinkController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(StoreLinkRequest $request)
    {
        $data = $request->safe()->only(['title', 'forward_query', 'callback_data']);

        $data['service_id'] = $request->user()->id;
        $data['domain_id'] = null;

        if ($request->safe()->input('domain')) {
            $domain = Domain::where('value', $request->safe()->string('domain'))->first();
            $data['domain_id'] = $domain->id;
        }

        $rulesData = $request->safe()->array('rules');

        return DB::transaction(function () use ($data, $rulesData) {
            $link = Link::create($data);

            foreach ($rulesData as $i => $ruleData) {
                $url = Modifier::wrap($ruleData['url'])
                    ->normalize()
                    ->sortQuery()
                    ->toString();

                $url = Url::firstOrCreate(['value' => $url]);

                $conditionId = null;

                if (! empty($ruleData['condition'])) {
                    $conditionData = $ruleData['condition'];

                    $json = json_encode($conditionData['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    $condition = Condition::query()
                        ->where('type', $conditionData['type'])
                        ->whereRaw('"data"::jsonb = ?::jsonb', [$json])
                        ->first();

                    $condition ??= Condition::create($conditionData);

                    $conditionId = $condition->id;
                }

                $link->rules()->create([
                    'url_id' => $url->id,
                    'condition_id' => $conditionId,
                    'priority' => $i + 1,
                ]);
            }

            $link->load('rules.condition');

            return LinkResource::make($link)
                ->response()
                ->setStatusCode(201);
        });
    }
}
