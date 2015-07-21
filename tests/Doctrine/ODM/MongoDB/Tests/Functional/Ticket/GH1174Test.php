<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

class GH1174Test extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    public function testDocumentPersisterDoesNotThrowStrategyUndefinedMessage()
    {
        $class = __NAMESPACE__ . '\GH1174Document';

        $schemaManager = $this->dm->getSchemaManager();
        $schemaManager->updateDocumentIndexes($class);

        $repository = $this->dm->getRepository($class);

        $this->assertCount(0, $repository->findAll());

        // Create, persist and flush initial object
        $doc1 = new GH1174Document();
        $doc1->id = 1;
        $doc1->name = 'foo';
        $doc1->nodes = [
            [ 'id' => '1' ],
            [ 'id' => '2' ],
        ];

        $this->dm->persist($doc1);
        $this->dm->flush();

        $criteria = [ 
            '$and' => [
                ['name' => 'foo'],
                ['nodes.id' => '1'],
                ['nodes.id' => '2']
            ]   
        ];  

        $doc1 = $repository->findOneBy($criteria);

        $this->assertTrue($doc1 instanceof \Doctrine\ODM\MongoDB\Tests\Functional\Ticket\GH1174Document);

        $this->assertCount(1, $repository->findAll());
    }
}

/** @ODM\Document */
class GH1174Document
{
    /** @ODM\Id(strategy="none", options={"type"="string"}) */
    public $id;

    /** @ODM\String */
    public $name;

    /** @ODM\Collection */
    public $nodes;
}
