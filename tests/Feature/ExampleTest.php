<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * 框架冒烟测试：健康检查端点（不依赖任何业务表）。
     * 首页等业务页面的覆盖见模块测试（Modules\CMS\Tests 等）。
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
    }
}
