<?php

namespace Doctrine\ODM\MongoDB\Tests\Persisters;

require_once __DIR__ . '/../../../../../TestInit.php';

use Doctrine\ODM\MongoDB\Persisters\BasicDocumentPersister,
    Documents\Account,
    Documents\Article,
    Documents\Address,
    Documents\Group,
    Documents\Phonenumber,
    Documents\Profile,
    Documents\File,
    Documents\User,
    Documents\Strategy,
    Documents\Message,
    Documents\Task,
    Documents\Project;

/**
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 */
class BasicDocumentPersisterTest extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    protected $persister;
    protected $classMetadata;

    public function setUp()
    {
        parent::setUp();
        $this->classMetadata = $this->dm->getClassMetadata('Documents\User');
        $this->persister = $this->getMock(
            'Doctrine\ODM\MongoDB\Persisters\BasicDocumentPersister',
            array('update', 'delete', 'executeInserts'),
            array($this->dm, $this->classMetadata)
        );
        $this->dm->getUnitOfWork()->setDocumentPersister(
            'Documents\User', $this->persister
        );
    }

    public function tearDown()
    {
        $this->persister = null;
        parent::tearDown();
    }

    /*
    public function testEmbededUpdate()
    {
        $subAddress =  new Address();
        $subAddress->setCity('Chicago');

        $address = new Address();
        $address->setCity('Nashville');
        $address->setSubAddress($subAddress);
        $address->count = 1;

        $project = new Project('Test');
        $project->setAddress($address);

        $originalData = array(
            'name' => 'test',
            'address' => clone $address
        );
        $this->dm->getUnitOfWork()->registerManaged($project, 'theprojectid', $originalData);
        $originalData = array(
            'city' => 'Nashville',
            'count' => 0,
            'subAddress' => clone $subAddress
        );
        $this->dm->getUnitOfWork()->registerManaged($address, 'theaddressid', $originalData);
        $originalData = array(
            'city' => 'Chicago',
            'count' => 0
        );
        $this->dm->getUnitOfWork()->registerManaged($subAddress, 'thesubaddressid', $originalData);

        $address->setCity('Atlanta');
        
        $this->dm->getUnitOfWork()->computeChangeSets();
        $changeSet = $this->dm->getUnitOfWork()->getDocumentChangeSet($address);

        $update = $this->persister->prepareUpdateData($project);
    }

    public function testOneEmbedded()
    {
        $address = new Address();
        $address->setCity('Nashville');

        $user = new User();
        $user->setUsername('jon');
        $user->setAddress($address);

        $originalData = array(
            'username' => 'jon',
            'address' => clone $address,
            'count' => 0,
            'hits' => 0,
            'createdAt' => $user->getCreatedAt()
        );
        $this->dm->getUnitOfWork()->registerManaged($user, 'theuserid', $originalData);
        $originalData = array(
            'city' => 'Nashville'
        );
        $this->dm->getUnitOfWork()->registerManaged($address, 'theaddressid', $originalData);
        $address->setCity('Atlanta');
        
        $this->dm->getUnitOfWork()->computeChangeSets();
        $changeSet = $this->dm->getUnitOfWork()->getDocumentChangeSet($address);
        $this->assertEquals(array('city' => array('Nashville', 'Atlanta'), 'count' => array(null, 0)), $changeSet);
        
        $update = $this->persister->prepareUpdateData($user);
        $this->assertEquals(array('$set' => array('address.city' => 'Atlanta'), '$inc' => array('address.count' => 0)), $update);
    }
    */

    public function testNewDocumentInsert()
    {
        $account = new Account();
        $account->setName('Jon Test Account');

        $user = new User();
        $user->setUsername('jon');
        $user->setPassword('changeme');
        $user->setAccount($account);

        $this->persister->expects($this->once())
            ->method('executeInserts');

        $this->dm->getUnitOfWork()->registerManaged($user, 'theid', array());
        $this->dm->getUnitOfWork()->computeChangeSets();
        $update = $this->persister->prepareUpdateData($user);
        $this->dm->flush();

        $this->assertTrue(array_key_exists($this->escape('set'), $update));
        $this->assertFalse(array_key_exists($this->escape('unset'), $update));
        $this->assertTrue(array_key_exists('username', $update[$this->escape('set')]));
        $this->assertTrue(array_key_exists('password', $update[$this->escape('set')]));
        $this->assertTrue(array_key_exists('account', $update[$this->escape('set')]));
        $this->assertTrue(array_key_exists($this->escape('ref'), $update[$this->escape('set')]['account']));
        $this->assertTrue(array_key_exists($this->escape('db'), $update[$this->escape('set')]['account']));
        $this->assertTrue(array_key_exists($this->escape('id'), $update[$this->escape('set')]['account']));
    }

    public function testSetStrategy()
    {
        $data = array(
            '_id' => 'testid',
            'logs' => array(
                array('name' => 'test'),
                array('name' => 'ok'),
                array('name' => 'test')
            ),
            'messages' => array(
                array('name' => 'Message1'),
                array('name' => 'Message2')
            ),
            'tasks' => array(
                array(
                    '$db' => 'dbname',
                    '$id' => 'id',
                    '$ref' => 'collname'
                )
            )
        );
        $test = new Strategy();
        $this->dm->getHydrator()->hydrate($test, $data);
        $this->dm->getUnitOfWork()->registerManaged($test, 'testid', $data);

        unset($test->logs[0]);
        $test->logs[] = 'whatever';
        $test->messages[] = new Message('Message3');
        $test->tasks[] = new Task('Task1');
        $test->tasks[] = new Task('Task2');

        $classMetadata = $this->dm->getClassMetadata('Documents\Strategy');
        $persister = $this->getMock(
            'Doctrine\ODM\MongoDB\Persisters\BasicDocumentPersister',
            array('update', 'delete', 'executeInserts'),
            array($this->dm, $classMetadata)
        );
        $this->dm->getUnitOfWork()->setDocumentPersister(
            'Documents\Strategy', $persister
        );

        $this->dm->getUnitOfWork()->computeChangeSets();
        $update = $persister->prepareUpdateData($test);

        $this->assertTrue(isset($update['$set']['logs']));
        $this->assertEquals(3, count($update['$set']['logs']));
        $this->assertTrue(isset($update['$set']['messages']));
        $this->assertEquals(3, count($update['$set']['messages']));
        $this->assertTrue(isset($update['$set']['tasks']));
        $this->assertEquals(3, count($update['$set']['tasks']));
        
    }

    public function testDocumentUpdate()
    {
        $this->dm->getUnitOfWork()->setDocumentPersister(
            'Documents\User', new BasicDocumentPersister($this->dm, $this->classMetadata)
        );

        $account = new Account();
        $account->setName('Jon Test Account');

        $user = new User();
        $user->setUsername('jon');
        $user->setPassword('changeme');
        $user->setAccount($account);

        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();
        unset($user, $account);

        $user = $this->dm->findOne('Documents\User');
        $this->assertEquals('jon', $user->getUsername());
        $this->assertEquals('changeme', $user->getPassword());
        $this->assertTrue($user->getAccount() instanceof Account);

        $user->setUsername(null);
        $this->dm->getUnitOfWork()->setDocumentPersister(
            'Documents\User', $this->persister
        );

        $this->persister->expects($this->once())
            ->method('update')
            ->with($user);

        $this->dm->getUnitOfWork()->computeChangeSets();
        $update = $this->persister->prepareUpdateData($user);

        $this->assertTrue(array_key_exists($this->escape('unset'), $update));
        $this->assertTrue(array_key_exists('username', $update[$this->escape('unset')]));
        $this->assertFalse(array_key_exists($this->escape('set'), $update));

        $this->dm->flush();
    }

    public function testAddGroups()
    {
        $account = new Account();
        $account->setName('Jon Test Account');

        $user = new User();
        $user->setUsername('jon');
        $user->setPassword('changeme');
        $user->setAccount($account);
        $user->setCount(5);

        $user->addGroup(new Group('administrator'));
        $user->addGroup(new Group('member'));
        $user->addGroup(new Group('moderator'));

        $this->dm->getUnitOfWork()->registerManaged($user, 'userid', array());
        $this->dm->getUnitOfWork()->registerManaged($account, 'accountid', array());

        $this->persister->expects($this->once())
            ->method('executeInserts');

        $this->dm->getUnitOfWork()->computeChangeSets();
        $update = $this->persister->prepareUpdateData($user);

        $this->assertTrue(array_key_exists($this->escape('set'), $update));
        $this->assertFalse(array_key_exists($this->escape('unset'), $update));
        $this->assertTrue(array_key_exists($this->escape('pushAll'), $update));
        $this->assertTrue(array_key_exists('groups', $update[$this->escape('pushAll')]));
        $this->assertEquals(3, count($update[$this->escape('pushAll')]['groups']));
        $this->assertFalse(array_key_exists($this->escape('pullAll'), $update));
        $this->assertTrue(array_key_exists($this->escape('inc'), $update));
        $this->assertEquals(5, $update[$this->escape('inc')]['count']);

        $user->setCount(20);
        $this->dm->getUnitOfWork()->computeChangeSets();
        $update = $this->persister->prepareUpdateData($user);
        $this->assertTrue(array_key_exists($this->escape('inc'), $update));
        $this->assertEquals(15, $update[$this->escape('inc')]['count']);

        $user->setCount(5);
        $this->dm->getUnitOfWork()->computeChangeSets();
        $update = $this->persister->prepareUpdateData($user);
        $this->assertTrue(array_key_exists($this->escape('inc'), $update));
        $this->assertEquals(-15, $update[$this->escape('inc')]['count']);

        $this->dm->flush();
    }

    public function testRemoveGroups()
    {
        $this->dm->getUnitOfWork()->setDocumentPersister(
            'Documents\User', new BasicDocumentPersister($this->dm, $this->classMetadata)
        );

        $account = new Account();
        $account->setName('Jon Test Account');

        $user = new User();
        $user->setUsername('jon');
        $user->setPassword('changeme');
        $user->setAccount($account);

        $user->addGroup(new Group('administrator'));
        $user->addGroup(new Group('member'));
        $user->addGroup(new Group('moderator'));

        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();

        unset($user, $account);

        $user = $this->dm->findOne('Documents\User');
        $this->assertEquals(3, count($user->getGroups()));
  
        $user->removeGroup('moderator');
        $user->removeGroup('member');

        $this->assertEquals(1, count($user->getGroups()));

        $this->dm->getUnitOfWork()->setDocumentPersister(
            'Documents\User', $this->persister
        );

        $this->persister->expects($this->once())
            ->method('update')
            ->with($user);

        $this->dm->getUnitOfWork()->computeChangeSets();
        $update = $this->persister->prepareUpdateData($user);

        $this->assertFalse(array_key_exists($this->escape('set'), $update));
        $this->assertFalse(array_key_exists($this->escape('unset'), $update));
        $this->assertTrue(array_key_exists($this->escape('pullAll'), $update));
        $this->assertTrue(array_key_exists('groups', $update[$this->escape('pullAll')]));
        $this->assertEquals(2, count($update[$this->escape('pullAll')]['groups']));
        $this->assertFalse(array_key_exists($this->escape('pushAll'), $update));

        $this->dm->flush();
    }

    public function testReplaceGroups()
    {
        $this->dm->getUnitOfWork()->setDocumentPersister(
            'Documents\User', new BasicDocumentPersister($this->dm, $this->classMetadata)
        );

        $account = new Account();
        $account->setName('Jon Test Account');

        $user = new User();
        $user->setUsername('jon');
        $user->setPassword('changeme');
        $user->setAccount($account);

        $user->addGroup(new Group('administrator'));
        $user->addGroup(new Group('member'));
        $user->addGroup(new Group('moderator'));

        $this->dm->persist($user);
        $this->dm->flush();
        $this->dm->clear();

        unset($user, $account);

        $user = $this->dm->findOne('Documents\User');

        $user->removeGroup('moderator');
        $user->removeGroup('member');

        $this->assertEquals(1, count($user->getGroups()));

        $user->addGroup(new Group('seller'));
        $user->addGroup(new Group('supplier'));

        $this->assertEquals(3, count($user->getGroups()));

        $this->dm->getUnitOfWork()->setDocumentPersister(
            'Documents\User', $this->persister
        );

        $this->persister->expects($this->once())
            ->method('update')
            ->with($user);

        $this->dm->getUnitOfWork()->computeChangeSets();
        $update = $this->persister->prepareUpdateData($user);

        $this->assertFalse(array_key_exists($this->escape('set'), $update));
        $this->assertFalse(array_key_exists($this->escape('unset'), $update));
        $this->assertTrue(array_key_exists($this->escape('pushAll'), $update));
        $this->assertTrue(array_key_exists('groups', $update[$this->escape('pushAll')]));
        $this->assertEquals(2, count($update[$this->escape('pushAll')]['groups']));
        $this->assertTrue(array_key_exists($this->escape('pullAll'), $update));
        $this->assertTrue(array_key_exists('groups', $update[$this->escape('pullAll')]));
        $this->assertEquals(2, count($update[$this->escape('pullAll')]['groups']));

        $this->dm->flush();
        $this->dm->clear();

        unset($user);

        $user = $this->dm->findOne('Documents\User');
        $this->assertEquals(3, count($user->getGroups()));
    }

    public function testCollectionField()
    {
        $classMetadata = $this->dm->getClassMetadata('Documents\Article');
        $persister = new BasicDocumentPersister($this->dm, $classMetadata);
        $this->dm->getUnitOfWork()->setDocumentPersister(
            'Documents\Article', $persister
        );

        $article = new Article();
        $article->setTitle('test');
        $article->setBody('test');
        $article->setCreatedAt('1985-09-04 00:00:00');

        $article->addTag('tag 1');
        $article->addTag('tag 2');
        $article->addTag('tag 3');
        $article->addTag('tag 4');

        $this->dm->persist($article);

        $this->dm->getUnitOfWork()->computeChangeSets();
        $update = $persister->prepareUpdateData($article);

        $this->assertTrue(array_key_exists($this->escape('pushAll'), $update));
        $this->assertTrue(array_key_exists('tags', $update[$this->escape('pushAll')]));
        $this->assertEquals(4, count($update[$this->escape('pushAll')]['tags']));
        $this->assertFalse(array_key_exists($this->escape('pullAll'), $update));

        $this->dm->flush();
        $this->dm->clear();
        unset($article);

        $article = $this->dm->findOne('Documents\Article');

        $this->assertEquals(array(
            'tag 1', 'tag 2', 'tag 3', 'tag 4',
        ), $article->getTags());

        $article->removeTag('tag 1');
        $article->removeTag('tag 3');
        $article->addTag('tag 5');
        $article->addTag('tag 6');

        $this->dm->getUnitOfWork()->computeChangeSets();
        $update = $persister->prepareUpdateData($article);

        $this->assertTrue(array_key_exists($this->escape('pushAll'), $update));
        $this->assertTrue(array_key_exists('tags', $update[$this->escape('pushAll')]));
        $this->assertEquals(2, count($update[$this->escape('pushAll')]['tags']));
        $this->assertTrue(array_key_exists($this->escape('pullAll'), $update));
        $this->assertTrue(array_key_exists('tags', $update[$this->escape('pullAll')]));
        $this->assertEquals(2, count($update[$this->escape('pullAll')]['tags']));

        $this->dm->flush();
        $this->dm->clear();
        unset($article);

        $article = $this->dm->findOne('Documents\Article');

        $this->assertEquals(array(
            'tag 2', 'tag 4', 'tag 5', 'tag 6'
        ), $article->getTags());
    }
}