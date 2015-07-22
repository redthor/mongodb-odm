<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Mapping\Driver\YamlDriver;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

class GH1174Test extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    /**
     * @test
     */
    public function compareDrivers()
    {   
        if (!class_exists('Symfony\Component\Yaml\Yaml', true)) {
            $this->markTestSkipped('This test requires the Symfony YAML component');
        }   

        // Yaml driver
        $ymlClass = __NAMESPACE__.'\YamlDriverGH1174Document';
        $ymlMappingDriver = new YamlDriver(__DIR__ . DIRECTORY_SEPARATOR . 'yaml');
        $metaFromYmlDriver = new ClassMetadata($ymlClass);
        $ymlMappingDriver->loadMetadataForClass($ymlClass, $metaFromYmlDriver);

        // Annotation driver
        $annoClass = __NAMESPACE__ . '\GH1174Document';
        $annoReader = new AnnotationReader();
        $annoMappingDriver = new AnnotationDriver($annoReader);
        $metaFromAnnoDriver = new ClassMetadata($annoClass);
        $annoMappingDriver->loadMetadataForClass($annoClass, $metaFromAnnoDriver);

        $ymlFieldMapping = $metaFromYmlDriver->getFieldMapping('nodes');
        $annoFieldMapping = $metaFromAnnoDriver->getFieldMapping('nodes');

        self::assertEquals($ymlFieldMapping, $annoFieldMapping);
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

class YamlDriverGH1174Document
{
    public $id;

    public $name;

    public $nodes;
}
