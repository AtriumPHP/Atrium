<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Tests\Fixtures\Resource\HookedTagResource;
use Atrium\Twig\Components\DataTable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * The table hides actions a user is not authorized for and refuses to run them,
 * and fires the delete lifecycle hooks when a delete is allowed.
 */
final class DataTableAuthorizationTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function setUp(): void
    {
        HookedTagResource::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        HookedTagResource::reset();
        restore_exception_handler();
    }

    public function testUnauthorizedRecordActionsAreHidden(): void
    {
        HookedTagResource::$allowEdit = false;
        HookedTagResource::$allowDelete = false;

        $html = $this->table()->render()->toString();

        self::assertStringNotContainsString('/edit"', $html, 'Edit links must be hidden.');
        self::assertStringNotContainsString('data-live-name-param="delete"', $html, 'Delete triggers must be hidden.');
    }

    public function testHeaderCreateActionHiddenWhenCreationDenied(): void
    {
        HookedTagResource::$allowCreate = false;

        self::assertStringNotContainsString('/hooked-tag/new', $this->table()->render()->toString());
    }

    public function testDeleteIsRefusedWhenUnauthorized(): void
    {
        HookedTagResource::$allowDelete = false;

        $component = $this->table();
        // A crafted request must neither open the prompt nor delete.
        $component->call('requestAction', ['name' => 'delete', 'id' => '3']);

        self::assertNull($this->instance($component)->confirmingAction);
        self::assertSame([], HookedTagResource::$deleted);
    }

    public function testDeleteRunsLifecycleHooksWhenAuthorized(): void
    {
        $component = $this->table();

        $component->call('requestAction', ['name' => 'delete', 'id' => '3']);
        $component->call('confirmAction');

        // afterDelete recorded the deleted record's slug.
        self::assertContains('tag-03', HookedTagResource::$deleted);
    }

    public function testBulkActionRefusedWhenPanelAbilityDenied(): void
    {
        HookedTagResource::$allowCreate = false;

        $component = $this->table();
        $component->call('toggleRecord', ['id' => '3']);
        // 'touch' is gated by the 'create' ability — a forged request must not run it.
        $component->call('requestBulkAction', ['name' => 'touch']);

        self::assertFalse(HookedTagResource::$bulkRan);
    }

    public function testBulkActionRunsWhenPanelAbilityAllowed(): void
    {
        $component = $this->table();
        $component->call('toggleRecord', ['id' => '3']);
        $component->call('requestBulkAction', ['name' => 'touch']);

        self::assertTrue(HookedTagResource::$bulkRan);
    }

    private function table(): TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:DataTable', [
            'resource' => 'hooked-tag',
            'pathPrefix' => '/admin',
        ]);
    }

    private function instance(TestLiveComponent $component): DataTable
    {
        $instance = $component->component();
        self::assertInstanceOf(DataTable::class, $instance);

        return $instance;
    }
}
