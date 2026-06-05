<?php

declare(strict_types=1);

namespace Atrium\Tests\DataProvider;

use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DoctrineRelationProvider;
use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationKind;
use Atrium\Tests\Fixtures\Doctrine\EntityManagerFactory;
use Atrium\Tests\Fixtures\Entity\Article;
use Atrium\Tests\Fixtures\Entity\Note;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class DoctrineRelationProviderTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineRelationProvider $provider;

    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::create();
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Article::class),
            $this->entityManager->getClassMetadata(Note::class),
        ]);
        $this->provider = new DoctrineRelationProvider($this->entityManager);
    }

    private function descriptor(): RelationDescriptor
    {
        return new RelationDescriptor(
            name: 'notes',
            kind: RelationKind::OneToMany,
            childEntityClass: Note::class,
            childIdField: 'id',
            parentIdField: 'id',
            recordTitleAttribute: 'body',
            foreignKey: 'articleId',
        );
    }

    public function testListRelatedScopesToTheParentForeignKey(): void
    {
        $a1 = new Article('First');
        $a2 = new Article('Second');
        $this->entityManager->persist($a1);
        $this->entityManager->persist($a2);
        $this->entityManager->flush();

        $this->entityManager->persist(new Note('n1', $a1->id));
        $this->entityManager->persist(new Note('n2', $a2->id));
        $this->entityManager->persist(new Note('n3', $a1->id));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $parent = $this->entityManager->find(Article::class, $a1->id);
        self::assertNotNull($parent);

        $rows = [...$this->provider->listRelated($this->descriptor(), $parent, new DataQuery())];
        self::assertCount(2, $rows);
        self::assertContainsOnlyInstancesOf(Note::class, $rows);
        self::assertSame(2, $this->provider->countRelated($this->descriptor(), $parent, new DataQuery()));
    }

    public function testListRelatedAppliesScopeFilters(): void
    {
        $article = new Article('First');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->entityManager->persist(new Note('keep', $article->id));
        $this->entityManager->persist(new Note('hide', $article->id));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $parent = $this->entityManager->find(Article::class, $article->id);
        self::assertNotNull($parent);

        // The DataQuery carries the target resource's scopeQuery conditions; a
        // child matching the FK but failing the scope must not surface (REL-09).
        $query = new DataQuery(filters: ['body' => 'keep']);
        $rows = [...$this->provider->listRelated($this->descriptor(), $parent, $query)];

        self::assertCount(1, $rows);
        self::assertInstanceOf(Note::class, $rows[0]);
        self::assertSame('keep', $rows[0]->body);
        self::assertSame(1, $this->provider->countRelated($this->descriptor(), $parent, $query));
    }

    public function testAssociateSetsForeignKeyAndDissociateClearsIt(): void
    {
        $article = new Article('First');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $note = new Note('floating', null);
        $this->entityManager->persist($note);
        $this->entityManager->flush();
        $noteId = $note->id;

        $parent = $this->entityManager->find(Article::class, $article->id);
        self::assertNotNull($parent);

        $this->provider->associate($this->descriptor(), $parent, $note);
        $this->entityManager->clear();
        self::assertSame($article->id, $this->entityManager->find(Note::class, $noteId)?->articleId);

        $reloaded = $this->entityManager->find(Note::class, $noteId);
        self::assertNotNull($reloaded);
        $this->provider->dissociate($this->descriptor(), $this->entityManager->find(Article::class, $article->id), $reloaded);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Note::class, $noteId)?->articleId);
    }
}
