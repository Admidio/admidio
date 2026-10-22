<?php

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Language;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Session\Entity\Session;
use Admidio\UI\Presenter\FormPresenter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

class SerializationEntityFixture extends Entity
{
    private string $sessionMarker = 'saved';
}

class SerializationCompatibilityTest extends TestCase
{
    private mixed $previousDatabase;
    private bool $hadDatabase;

    protected function setUp(): void
    {
        $this->hadDatabase = array_key_exists('gDb', $GLOBALS);
        $this->previousDatabase = $GLOBALS['gDb'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->hadDatabase) {
            $GLOBALS['gDb'] = $this->previousDatabase;
        } else {
            unset($GLOBALS['gDb']);
        }
    }

    public function testRoundTripRestoresPropertiesAndGlobalDatabase(): void
    {
        $GLOBALS['gDb'] = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();
        $oldDatabase = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();

        foreach (array(
            SerializationEntityFixture::class => array('db', 'sessionMarker'),
            ItemsData::class => array('mDb', 'organizationId'),
            ProfileFields::class => array('db', 'mUserId'),
            SettingsManager::class => array('db', 'orgId')
        ) as $class => $names) {
            $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();
            (new ReflectionProperty($object, $names[0]))->setValue($object, $oldDatabase);
            if ($class === ItemsData::class) {
                (new ReflectionProperty($object, 'organizationId'))->setValue($object, 42);
            } elseif ($class === SettingsManager::class) {
                (new ReflectionProperty($object, 'orgId'))->setValue($object, 42);
            }

            // __serialize() retains the same mangled keys as the old object format.
            $restored = unserialize(serialize($object));
            self::assertSame($GLOBALS['gDb'], (new ReflectionProperty($restored, $names[0]))->getValue($restored));
            if ($class === ItemsData::class || $class === SettingsManager::class) {
                self::assertSame(42, (new ReflectionProperty($restored, $names[1]))->getValue($restored));
            } elseif ($class === SerializationEntityFixture::class) {
                self::assertSame('saved', (new ReflectionProperty($restored, 'sessionMarker'))->getValue($restored));
            }
        }
    }

    public function testPreviouslySerializedPrivateAndProtectedPropertiesRemainReadable(): void
    {
        $GLOBALS['gDb'] = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();

        $settings = unserialize($this->legacyObject(SettingsManager::class, array(
            "\0" . SettingsManager::class . "\0orgId" => 12,
            "\0" . SettingsManager::class . "\0settings" => array('example' => 'value')
        )));
        self::assertSame(12, (new ReflectionProperty($settings, 'orgId'))->getValue($settings));
        self::assertSame(array('example' => 'value'), (new ReflectionProperty($settings, 'settings'))->getValue($settings));
        self::assertSame($GLOBALS['gDb'], (new ReflectionProperty($settings, 'db'))->getValue($settings));

        $entity = unserialize($this->legacyObject(SerializationEntityFixture::class, array(
            "\0*\0tableName" => 'adm_example',
            "\0" . SerializationEntityFixture::class . "\0sessionMarker" => 'legacy'
        )));
        self::assertSame('adm_example', (new ReflectionProperty($entity, 'tableName'))->getValue($entity));
        self::assertSame('legacy', (new ReflectionProperty($entity, 'sessionMarker'))->getValue($entity));
        self::assertSame($GLOBALS['gDb'], (new ReflectionProperty($entity, 'db'))->getValue($entity));

        $language = unserialize($this->legacyObject(Language::class, array(
            "\0" . Language::class . "\0language" => 'de',
            "\0" . Language::class . "\0textCache" => array('SYS_TEST' => 'Test')
        )));
        self::assertSame('de', (new ReflectionProperty($language, 'language'))->getValue($language));
        self::assertSame(array('SYS_TEST' => 'Test'), (new ReflectionProperty($language, 'textCache'))->getValue($language));

        $form = unserialize($this->legacyObject(FormPresenter::class, array(
            "\0*\0id" => 'legacy-form',
            "\0*\0showRequiredFields" => true
        )));
        self::assertSame('legacy-form', (new ReflectionProperty($form, 'id'))->getValue($form));
        self::assertTrue((new ReflectionProperty($form, 'showRequiredFields'))->getValue($form));

        $database = unserialize($this->legacyObject(Database::class, array(
            "\0*\0engine" => 'mysql',
            "\0*\0host" => 'localhost',
            "\0*\0port" => null,
            "\0*\0dbName" => 'admidio_test',
            "\0*\0username" => 'admidio',
            "\0*\0password" => null,
            "\0*\0options" => array()
        )));
        self::assertSame('admidio_test', (new ReflectionProperty($database, 'dbName'))->getValue($database));
    }

    public function testUnknownLegacyPropertiesAreIgnored(): void
    {
        $GLOBALS['gDb'] = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();

        $entity = unserialize($this->legacyObject(SerializationEntityFixture::class, array(
            'legacyExtraProperty' => 'obsolete',
            "\0*\0removedProtectedProperty" => 'obsolete',
            "\0" . SerializationEntityFixture::class . "\0removedPrivateProperty" => 'obsolete',
            "\0*\0tableName" => 'adm_example',
            "\0" . SerializationEntityFixture::class . "\0sessionMarker" => 'legacy'
        )));

        self::assertFalse(property_exists($entity, 'legacyExtraProperty'));
        self::assertFalse(property_exists($entity, 'removedProtectedProperty'));
        self::assertFalse(property_exists($entity, 'removedPrivateProperty'));
        self::assertSame('adm_example', (new ReflectionProperty($entity, 'tableName'))->getValue($entity));
        self::assertSame('legacy', (new ReflectionProperty($entity, 'sessionMarker'))->getValue($entity));
        self::assertSame($GLOBALS['gDb'], (new ReflectionProperty($entity, 'db'))->getValue($entity));
    }

    public function testSelectiveSerializationKeepsOnlyLegacyProperties(): void
    {
        $language = (new ReflectionClass(Language::class))->newInstanceWithoutConstructor();
        $languageData = $language->__serialize();
        self::assertArrayHasKey("\0" . Language::class . "\0language", $languageData);
        self::assertArrayNotHasKey("\0" . Language::class . "\0xmlLanguageObjects", $languageData);
        self::assertInstanceOf(Language::class, unserialize(serialize($language)));

        $form = (new ReflectionClass(FormPresenter::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($form, 'showRequiredFields'))->setValue($form, true);
        $formData = $form->__serialize();
        self::assertArrayHasKey("\0*\0showRequiredFields", $formData);
        self::assertArrayNotHasKey("\0*\0htmlPage", $formData);
        self::assertTrue((new ReflectionProperty(FormPresenter::class, 'showRequiredFields'))->getValue(unserialize(serialize($form))));

        $settings = (new ReflectionClass(SettingsManager::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($settings, 'orgId'))->setValue($settings, 7);
        self::assertCount(2, $settings->__serialize());

        $database = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();
        foreach (array('engine' => 'mysql', 'host' => 'localhost', 'port' => null,
                       'dbName' => 'example', 'username' => null, 'password' => null, 'options' => array()) as $name => $value) {
            (new ReflectionProperty($database, $name))->setValue($database, $value);
        }
        self::assertCount(7, $database->__serialize());
        self::assertArrayNotHasKey("\0*\0pdo", $database->__serialize());
        self::assertSame('mysql', (new ReflectionProperty(Database::class, 'engine'))->getValue(unserialize(serialize($database))));
    }

    public function testSessionObjectReferencesSurviveSerialization(): void
    {
        $GLOBALS['gDb'] = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();
        $session = (new ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $sharedObject = new \stdClass();
        $session->addObject('first', $sharedObject);
        $session->addObject('second', $sharedObject);

        $restored = unserialize(serialize($session));
        $first =& $restored->getObject('first');
        $first = new \stdClass();

        self::assertSame($first, $restored->getObject('second'));
    }

    /** @param array<string,mixed> $properties */
    private function legacyObject(string $class, array $properties): string
    {
        $body = '';
        foreach ($properties as $name => $value) {
            $body .= serialize($name) . serialize($value);
        }

        return 'O:' . strlen($class) . ':"' . $class . '":' . count($properties) . ':{' . $body . '}';
    }
}
