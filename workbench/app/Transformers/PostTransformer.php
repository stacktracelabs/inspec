<?php

namespace Workbench\App\Transformers;

use League\Fractal\TransformerAbstract;
use StackTrace\Inspec\ExpandCollection;
use StackTrace\Inspec\ExpandItem;
use StackTrace\Inspec\Schema;

class PostTransformer extends TransformerAbstract
{
    protected array $availableIncludes = ['author', 'tags', 'coAuthors'];

    #[Schema(object: [
        'id:integer' => 'Post identifier',
        'title:string' => 'Post title',
    ])]
    public function transform(array $post): array
    {
        return $post;
    }

    #[ExpandItem(UserTransformer::class)]
    public function includeAuthor(array $post)
    {
        //
    }

    #[ExpandCollection(TagTransformer::class)]
    public function includeTags(array $post)
    {
        //
    }

    #[ExpandItem([UserTransformer::class, TeamTransformer::class])]
    public function includeCoAuthors(array $post)
    {
        //
    }
}
