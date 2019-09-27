<?php

namespace App\Transformer\Api\Mastodon\v1;

use App\Status;
use League\Fractal;
use Cache;

class StatusTransformer extends Fractal\TransformerAbstract
{
    protected $defaultIncludes = [
        'account',
        'media_attachments',
        'mentions',
        'tags',
    ];

    public function transform(Status $status)
    {
        return [
            'id'                        => (string) $status->id,
            'uri'                       => $status->url(),
            'url'                       => $status->url(),
            'in_reply_to_id'            => $status->in_reply_to_id,
            'in_reply_to_account_id'    => $status->in_reply_to_profile_id,
            'reblog'                    => null,
            'content'                   => $status->rendered ?? $status->caption,
            'created_at'                => $status->created_at->toIso8601ZuluString(),
            'emojis'                    => [],
            'reblogs_count'             => $status->reblogs_count != 0 ? $status->reblogs_count: $status->shares()->count(),
            'favourites_count'          => $status->likes_count != 0 ? $status->likes_count: $status->likes()->count(),
            'reblogged'                 => null,
            'favourited'                => null,
            'muted'                     => null,
            'sensitive'                 => (bool) $status->is_nsfw,
            'spoiler_text'              => $status->cw_summary ?? '',
            'visibility'                => $status->visibility ?? $status->scope,
            'application'               => [
                'name'      => 'web',
                'website'   => null
             ],
            'language'                  => null,
            'pinned'                    => null,
            'mentions'                  => [],

            'parent'                    => [],
            'place'                     => $status->place
        ];
    }

    public function includeAccount(Status $status)
    {
        $account = $status->profile;

        return $this->item($account, new AccountTransformer());
    }

    public function includeMediaAttachments(Status $status)
    {
        return Cache::remember('status:transformer:media:attachments:'.$status->id, now()->addDays(14), function() use($status) {
            if(in_array($status->type, ['photo', 'video', 'photo:album', 'loop', 'photo:video:album'])) {
                $media = $status->media()->orderBy('order')->get();
                return $this->collection($media, new MediaTransformer());
            }
        });
    }

    public function includeMentions(Status $status)
    {
        $mentions = $status->mentions;

        return $this->collection($mentions, new MentionTransformer());
    }

    public function includeTags(Status $status)
    {
        $hashtags = $status->hashtags;

        return $this->collection($hashtags, new HashtagTransformer());
    }
}