<?php

declare(strict_types=1);

namespace Atrium\Tests\DataProvider;

use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DoctrineRelationProvider;
use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationKind;
use Atrium\Tests\Fixtures\Doctrine\EntityManagerFactory;
use Atrium\Tests\Fixtures\Entity\Article;
use Atrium\Tests\Fixtures\Entity\Course;
use Atrium\Tests\Fixtures\Entity\Note;
use Atrium\Tests\Fixtures\Entity\Student;
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
            $this->entityManager->getClassMetadata(Course::class),
            $this->entityManager->getClassMetadata(Student::class),
        ]);
        // The pivot is not a mapped entity — create it directly (REL-02).
        $this->entityManager->getConnection()->executeStatement(
            'CREATE TABLE course_student (course_id INTEGER NOT NULL, student_id INTEGER NOT NULL, role VARCHAR(255) DEFAULT NULL)'
        );
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

    private function pivotDescriptor(): RelationDescriptor
    {
        return new RelationDescriptor(
            name: 'students',
            kind: RelationKind::ManyToMany,
            childEntityClass: Student::class,
            childIdField: 'id',
            parentIdField: 'id',
            recordTitleAttribute: 'name',
            pivotTable: 'course_student',
            pivotParentKey: 'course_id',
            pivotRelatedKey: 'student_id',
            pivotColumns: ['role'],
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
        $reloadedArticle = $this->entityManager->find(Article::class, $article->id);
        self::assertNotNull($reloadedArticle);
        $this->provider->dissociate($this->descriptor(), $reloadedArticle, $reloaded);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Note::class, $noteId)?->articleId);
    }

    public function testListLinkableReturnsOnlyUnlinkedChildren(): void
    {
        $article = new Article('First');
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->entityManager->persist(new Note('linked', $article->id));
        $this->entityManager->persist(new Note('free', null));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $parent = $this->entityManager->find(Article::class, $article->id);
        self::assertNotNull($parent);

        $rows = [...$this->provider->listLinkable($this->descriptor(), $parent, new DataQuery())];
        self::assertCount(1, $rows);
        self::assertInstanceOf(Note::class, $rows[0]);
        self::assertSame('free', $rows[0]->body);
        self::assertSame(1, $this->provider->countLinkable($this->descriptor(), $parent, new DataQuery()));
    }

    public function testManyToManyListRelatedJoinsThroughThePivot(): void
    {
        $course = new Course('Math');
        $this->entityManager->persist($course);
        $s1 = new Student('Ada');
        $s2 = new Student('Bo');
        $s3 = new Student('Cy');
        foreach ([$s1, $s2, $s3] as $student) {
            $this->entityManager->persist($student);
        }
        $this->entityManager->flush();

        $conn = $this->entityManager->getConnection();
        $conn->insert('course_student', ['course_id' => $course->id, 'student_id' => $s1->id]);
        $conn->insert('course_student', ['course_id' => $course->id, 'student_id' => $s3->id]);
        $this->entityManager->clear();

        $parent = $this->entityManager->find(Course::class, $course->id);
        self::assertNotNull($parent);

        $rows = [...$this->provider->listRelated($this->pivotDescriptor(), $parent, new DataQuery())];
        self::assertCount(2, $rows);
        self::assertContainsOnlyInstancesOf(Student::class, $rows);
        self::assertSame(2, $this->provider->countRelated($this->pivotDescriptor(), $parent, new DataQuery()));
    }

    public function testManyToManyListLinkableExcludesAlreadyLinked(): void
    {
        $course = new Course('Math');
        $this->entityManager->persist($course);
        $s1 = new Student('Ada');
        $s2 = new Student('Bo');
        foreach ([$s1, $s2] as $student) {
            $this->entityManager->persist($student);
        }
        $this->entityManager->flush();
        $this->entityManager->getConnection()->insert('course_student', ['course_id' => $course->id, 'student_id' => $s1->id]);
        $this->entityManager->clear();

        $parent = $this->entityManager->find(Course::class, $course->id);
        self::assertNotNull($parent);

        $rows = [...$this->provider->listLinkable($this->pivotDescriptor(), $parent, new DataQuery())];
        self::assertCount(1, $rows);
        self::assertInstanceOf(Student::class, $rows[0]);
        self::assertSame('Bo', $rows[0]->name);
        self::assertSame(1, $this->provider->countLinkable($this->pivotDescriptor(), $parent, new DataQuery()));
    }

    public function testManyToManyAttachInsertsPivotRowWithColumnsAndDetachRemovesIt(): void
    {
        $course = new Course('Math');
        $student = new Student('Ada');
        $this->entityManager->persist($course);
        $this->entityManager->persist($student);
        $this->entityManager->flush();

        $this->provider->attach($this->pivotDescriptor(), $course, $student, ['role' => 'lead']);

        $conn = $this->entityManager->getConnection();
        self::assertSame('lead', $conn->fetchOne('SELECT role FROM course_student WHERE course_id = ? AND student_id = ?', [$course->id, $student->id]));

        $this->provider->detach($this->pivotDescriptor(), $course, $student);
        self::assertFalse($conn->fetchOne('SELECT role FROM course_student WHERE course_id = ? AND student_id = ?', [$course->id, $student->id]));
    }
}
