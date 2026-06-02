<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Twig\Components\DataTable;
use Atrium\Twig\Components\Form;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * A resource's scopeQuery() narrows which records it exposes: the list and the
 * count only see scoped rows, and single-record resolution honours the same
 * scope so an out-of-scope record's data is never loaded into the form.
 *
 * ScopedTagResource exposes only active tags; SampleData seeds 12 tags with the
 * even-id ones active, so exactly 6 are in scope.
 */
final class QueryScopingTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testListAndCountOnlySeeScopedRecords(): void
    {
        $table = $this->createLiveComponent('Atrium:DataTable', [
            'resource' => 'scoped-tag',
            'pathPrefix' => '/admin',
        ]);

        $instance = $table->component();
        self::assertInstanceOf(DataTable::class, $instance);
        self::assertSame(6, $instance->getTotalCount(), 'Only the 6 active tags are in scope.');

        $html = $table->render()->toString();
        self::assertStringContainsString('Tag 02', $html, 'An active tag is listed.');
        self::assertStringNotContainsString('Tag 01', $html, 'An inactive tag is scoped out of the list.');
    }

    public function testFormLoadsAScopedRecordButNotAnOutOfScopeOne(): void
    {
        // id 2 is active → in scope → its data fills the form.
        $inScope = $this->createLiveComponent('Atrium:Form', [
            'resource' => 'scoped-tag',
            'entityId' => '2',
        ]);
        $loaded = $inScope->component();
        self::assertInstanceOf(Form::class, $loaded);
        self::assertSame('Tag 02', $loaded->formData['name'] ?? null);

        // id 1 is inactive → out of scope → resolves to nothing, so the form
        // shows defaults, never the hidden record's data.
        $outOfScope = $this->createLiveComponent('Atrium:Form', [
            'resource' => 'scoped-tag',
            'entityId' => '1',
        ]);
        $blank = $outOfScope->component();
        self::assertInstanceOf(Form::class, $blank);
        self::assertSame('', $blank->formData['name'] ?? null, 'An out-of-scope record is not exposed.');
    }
}
