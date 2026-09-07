<?php

namespace Modules\CMS\Services;

use App\Models\User;
use App\Support\UserAgentParser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use Miran\Mksine\Models\Comment;

/**
 * CMS 文章评论服务。
 * 底座 comments 表已支持多态/parent_id 楼中楼/user_agent 追踪；
 * 本服务负责：Markdown 安全渲染、提交入库、树形读取与 UA 识别装饰。
 */
final class CommentService
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment(['html_input' => 'escape']); // 用户输入的原始 HTML 转义，防 XSS
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new StrikethroughExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new TaskListExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * 发表评论（登录用户或游客——游客需昵称+邮箱，user_id 为空）。
     *
     * @param  array{content: string, parent_id?: int|null, author_name?: string|null, author_email?: string|null}  $data
     */
    public function store(?Authenticatable $user, int $postId, array $data, string $ip, string $userAgent): Comment
    {
        return Comment::create([
            'commentable_type' => \Miran\Mksine\Models\Post::class,
            'commentable_id' => $postId,
            'user_id' => $user ? (int) $user->getAuthIdentifier() : null,
            'parent_id' => $data['parent_id'] ?? null,
            'author_name' => $data['author_name'] ?? null,
            'author_email' => $data['author_email'] ?? null,
            'content' => trim((string) $data['content']),
            'status' => 'approved',
            'ip_address' => $ip,
            'user_agent' => mb_substr($userAgent, 0, 500),
        ]);
    }

    /** 顶层评论默认条数。 */
    public const TOP_LIMIT = 20;

    /** 单个父评论下最多加载的回复数（防热门文章响应体积无限膨胀）。 */
    public const REPLY_LIMIT = 50;

    /**
     * 某文章的评论树（顶层按时间正序，子回复挂在 replies 下），
     * 每条评论带 Markdown 渲染后的 html 与 UA 识别信息。
     *
     * 回复数量设上限：旧实现把全部回复一次性 attach 且无条数/层级限制，
     * 热门文章会让单次响应与内存占用线性膨胀（P2-8）。
     */
    public function treeFor(int $postId, int $topLimit = self::TOP_LIMIT): array
    {
        // 只取本页需要的评论：先定位顶层 ID，再连带其回复，避免整表捞回
        $topIds = Comment::query()
            ->where('commentable_type', \Miran\Mksine\Models\Post::class)
            ->where('commentable_id', $postId)
            ->where('status', 'approved')
            ->whereNull('parent_id')
            ->orderBy('created_at')
            ->limit($topLimit)
            ->pluck('id');

        if ($topIds->isEmpty()) {
            return [];
        }

        $countByParent = Comment::query()
            ->selectRaw('parent_id, COUNT(*) as aggregate')
            ->whereIn('parent_id', $topIds)
            ->where('status', 'approved')
            ->groupBy('parent_id')
            ->pluck('aggregate', 'parent_id');

        // 先只取回复 ID（轻量），按父分组后每组截断到 REPLY_LIMIT，
        // 再回查保留行的完整数据 —— 这样响应体积与加载的模型数都是有限的
        $replyIdsByParent = Comment::query()
            ->select(['id', 'parent_id'])
            ->whereIn('parent_id', $topIds)
            ->where('status', 'approved')
            ->orderBy('created_at')
            ->get()
            ->groupBy('parent_id')
            ->map(fn ($rows) => $rows->pluck('id')->take(self::REPLY_LIMIT)->all());

        $keptReplyIds = $replyIdsByParent->flatten()->all();

        $comments = Comment::query()
            ->whereIn('id', $topIds->merge($keptReplyIds)->all())
            ->with(['user:id,name'])
            ->orderBy('created_at')
            ->get();

        // 用户 ID → 昵称映射（楼中楼 @ 回复目标）
        $usersById = User::query()
            ->whereIn('id', $comments->pluck('user_id')->filter()->unique())
            ->pluck('name', 'id');

        $decorated = $comments->map(fn (Comment $c) => $this->decorate($c, $usersById))->keyBy('id');

        $children = $decorated->whereNotNull('parent_id')->groupBy('parent_id');

        return $topIds
            ->map(fn ($id) => $decorated->get($id))
            ->filter()
            ->map(function ($comment) use ($children, $countByParent) {
                $total = (int) ($countByParent[$comment['id']] ?? 0);

                $comment['replies'] = $this->attachReplies(
                    $children->get($comment['id'], collect()),
                    $comment['author_name'],
                );
                $comment['replies_total'] = $total;
                $comment['hidden_replies'] = max(0, $total - count($comment['replies']));

                return $comment;
            })
            ->values()
            ->all();
    }

    private function attachReplies($replies, ?string $parentAuthorName = null): array
    {
        return $replies->map(function ($reply) use ($parentAuthorName) {
            $reply['reply_to'] = $parentAuthorName;
            $reply['replies'] = [];

            return $reply;
        })->values()->all();
    }

    /**
     * 单条评论装饰：Markdown HTML、作者名、UA 解析。
     */
    private function decorate(Comment $comment, $usersById): array
    {
        $ua = UserAgentParser::parse($comment->user_agent);

        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'author_name' => $comment->user?->name ?? ($comment->author_name ?? '游客'),
            'author_id' => $comment->user_id,
            'html' => $this->converter->convert($comment->content)->getContent(),
            'created_at' => optional($comment->created_at)->diffForHumans(),
            'os' => $ua['os'],
            'browser' => $ua['browser'],
            'device' => $ua['device'],
        ];
    }
}
