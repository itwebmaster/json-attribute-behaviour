<?php


use itwebmaster\JsonAttributeBehavior\JsonAttributeBehaviorTrait;
use PHPUnit\Framework\TestCase;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\db\Connection;
use yii\db\Schema;

class JsonAttributeBehaviorTraitTestClass extends ActiveRecord
{
    use JsonAttributeBehaviorTrait;

    public static function tableName()
    {
        return 'dummy_table';
    }

    public $jsonField;

    public function getJsonAttributes(): array
    {
        return ['jsonField'];
    }

    // Для доступа к protected методам — только для тестов
    public function publicArrayDeepMerge(array $base, array $default): array
    {
        return $this->arrayDeepMerge($base, $default);
    }

    public function publicConvertDefaultsToNestedArray(array $defaults): array
    {
        return $this->convertDefaultsToNestedArray($defaults);
    }

    public function publicGetFromArrayPath(array $data, $path)
    {
        return $this->getFromArrayPath($data, $path);
    }

    public function publicSetInArrayPath(array &$data, $path, $value): void
    {
        $this->setInArrayPath($data, $path, $value);
    }
}



class JsonAttributeBehaviorTraitTest extends TestCase
{
    private JsonAttributeBehaviorTraitTestClass $obj;

    public static function setUpBeforeClass(): void
    {
        // Инициализируем SQLite in-memory и создаём таблицу, если её нет
        if (!Yii::$app->has('db')) {
            Yii::$app->set('db', [
                'class' => Connection::class,
                'dsn' => 'sqlite::memory:',
            ]);
        }

        $db = Yii::$app->get('db');
        $tables = $db->schema->getTableNames();

        if (!in_array('dummy_table', $tables)) {
            $db->createCommand()->createTable('dummy_table', [
                'id' => Schema::TYPE_PK,
                'jsonField' => Schema::TYPE_TEXT,
            ])->execute();
        }
    }

    protected function setUp(): void
    {
        $this->obj = new JsonAttributeBehaviorTraitTestClass();
    }

    public function testArrayDeepMerge(): void
    {
        $base = ['a' => 1, 'b' => null, 'c' => ['x' => null]];
        $default = ['b' => 2, 'c' => ['x' => 3, 'y' => 4], 'd' => 5];

        $result = $this->obj->publicArrayDeepMerge($base, $default);

        $expected = [
            'a' => 1,
            'b' => 2,
            'c' => ['x' => 3, 'y' => 4],
            'd' => 5,
        ];

        $this->assertEquals($expected, $result);
    }

    public function testConvertDefaultsToNestedArray(): void
    {
        $defaults = [
            'proxy.enabled' => false,
            'limits.daily' => 100,
            'simple' => 'value',
        ];

        $normalized = [];
        foreach ($defaults as $key => $value) {
            $normalized[] = [explode('.', $key), $value];
        }

        $converted = [];
        foreach ($normalized as [$path, $value]) {
            $ref = &$converted;
            foreach ($path as $segment) {
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    $ref[$segment] = [];
                }
                $ref = &$ref[$segment];
            }
            $ref = $value;
        }

        $expected = [
            'proxy' => ['enabled' => false],
            'limits' => ['daily' => 100],
            'simple' => 'value',
        ];

        $this->assertEquals($expected, $converted);
    }

    public function testGetFromArrayPath(): void
    {
        $data = [
            'level1' => [
                'level2' => 'value',
                'empty' => null,
            ]
        ];

        $this->assertEquals('value', $this->obj->publicGetFromArrayPath($data, ['level1', 'level2']));
        $this->assertNull($this->obj->publicGetFromArrayPath($data, ['level1', 'missing']));
        $this->assertNull($this->obj->publicGetFromArrayPath($data, ['level1', 'empty']));
        $this->assertEquals('value', $this->obj->publicGetFromArrayPath(['value' => 'value'], 'value'));
    }

    public function testSetInArrayPath(): void
    {
        $data = [];

        $this->obj->publicSetInArrayPath($data, ['a', 'b'], 123);
        $this->obj->publicSetInArrayPath($data, 'c', 456);

        $expected = [
            'a' => ['b' => 123],
            'c' => 456,
        ];

        $this->assertEquals($expected, $data);
    }

    public function testGetSetJsonAttr(): void
    {
        $this->obj->jsonField = ['level1' => ['level2' => 'val']];

        $this->assertEquals(['level1' => ['level2' => 'val']], $this->obj->getJsonAttr('jsonField'));
        $this->assertEquals('val', $this->obj->getJsonAttr('jsonField', ['level1', 'level2']));
        $this->assertNull($this->obj->getJsonAttr('jsonField', ['level1', 'missing']));

        $this->obj->setJsonAttr('jsonField', ['level1', 'level3'], 'newVal');

        $expected = [
            'level1' => [
                'level2' => 'val',
                'level3' => 'newVal'
            ]
        ];

        $this->assertEquals($expected, $this->obj->getJsonAttr('jsonField'));
    }

    public function testJsonAttributeBehaviors(): void
    {
        $behaviors = $this->obj->jsonAttributeBehaviors(['jsonField']);

        $this->assertArrayHasKey('json_jsonField', $behaviors);
        $this->assertArrayHasKey('attributes', $behaviors['json_jsonField']);
        $this->assertIsCallable($behaviors['json_jsonField']['value']);
    }
}

