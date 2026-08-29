<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Captcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Miran\Mksine\Models\Post;
use Modules\CMS\Services\CommentService;

class CommentController extends Controller
{
    /**
     * 发表评论：登录用户直接评论；游客需填昵称 + 邮箱 + 验证码。
     */
    public function store(Request $request, string $slug, CommentService $comments): RedirectResponse
    {
        $post = Post::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        $user = $request->user();

        $rules = [
            'content' => ['required', 'string', 'min:2', 'max:2000'],
            // 楼中楼：父评论必须属于同一篇文章
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')->where(
                    fn ($q) => $q->where('commentable_type', Post::class)->where('commentable_id', $post->id)
                ),
            ],
        ];

        if (! $user) {
            // 游客：昵称 + 邮箱 + 验证码（math / Turnstile）
            $rules['author_name'] = ['required', 'string', 'max:50'];
            $rules['author_email'] = ['required', 'email', 'max:191'];
        }

        $data = $request->validate($rules);

        // 验证码：仅游客需要（登录用户可信，违规可直接封号）；math / Turnstile 驱动可切换
        if (! $user) {
            Captcha::verify($request);
        }

        $comments->store(
            $user,
            (int) $post->id,
            [
                'content' => $data['content'],
                'parent_id' => $data['parent_id'] ?? null,
                'author_name' => $data['author_name'] ?? null,
                'author_email' => $data['author_email'] ?? null,
            ],
            (string) $request->ip(),
            (string) $request->userAgent(),
        );

        return redirect()->back()->with('success', '评论发表成功');
    }
}
