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

        if ($request->safe()->has('domain')) {
            $domain = Domain::where('value', $request->safe()->string('domain'))->first();
            $data['domain_id'] = $domain->id;
        }

        $rules = $request->safe()->array('rules');

        return DB::transaction(function () use ($data, $rules) {
            $link = Link::create($data);

            foreach ($rules as $i => $rule) {
                $url = Modifier::from($rule['url'])
                    ->sortQuery()
                    ->getUriString();

                $url = Url::firstOrCreate(['value' => $url]);

                $conditionId = null;

                if (! empty($rule['condition'])) {
                    $condition = Condition::firstOrCreate([
                        'type' => $rule['condition']['type'],
                        'data' => $rule['condition']['data'],
                    ]);

                    $conditionId = $condition->id;
                }

                $link->rules()->create([
                    'url_id' => $url->id,
                    'condition_id' => $conditionId,
                    'priority' => $i + 1,
                ]);
            }

            $link->load('rules');

            return LinkResource::make($link)
                ->response()
                ->setStatusCode(201);
        });
    }
}
