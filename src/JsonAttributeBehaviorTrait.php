<?php

namespace itwebmaster\JsonAttributeBehavior;

use yii\behaviors\AttributeBehavior;
use yii\db\BaseActiveRecord;
use yii\helpers\Json;

/**
 * Trait JsonAttributeBehaviorTrait
 *
 * Трейт для удобной работы с JSON-полями в моделях Yii2 (ActiveRecord и Model).
 *
 * Особенности:
 * - Автоматическое кодирование/декодирование JSON при сохранении и загрузке из БД
 * - Доступ к вложенным элементам JSON через пути (строкой с точками или массивом ключей)
 * - Установка вложенных значений по пути
 * - Утилиты для глубокого слияния массивов и конвертации плоских defaults в вложенные
 *
 * ## Использование
 *
 * В модели нужно определить метод `getJsonAttributes()`, который возвращает список JSON-полей:
 * ```php
 * public function getJsonAttributes(): array
 * {
 *     return ['settings', 'options'];
 * }
 * ```
 *
 * Трейт автоматически добавит в `behaviors()` поведение для кодирования/декодирования JSON.
 *
 * ## Основные методы
 *
 * - `getJsonAttr(string $attr, array|string|null $path = null, $default = null)` — получить значение из JSON-поля по пути
 * - `setJsonAttr(string $attr, array|string $path, $value)` — установить значение по пути в JSON-поле
 * - Вспомогательные: `arrayDeepMerge()`, `convertDefaultsToNestedArray()`, `getFromArrayPath()`, `setInArrayPath()`
 *
 * ## Пример
 *
 * ```php
 * $model->setJsonAttr('settings', 'notifications.email', true);
 * $emailNotificationsEnabled = $model->getJsonAttr('settings', ['notifications', 'email'], false);
 * ```
 *
 * ## Важно
 * Метод `getJsonAttributes()` должен быть реализован в модели, иначе трейт не подключит поведение.
 *
 * @property array $_jsonAttributes Список JSON-атрибутов (устанавливается автоматически)
 */
trait JsonAttributeBehaviorTrait
{
    protected array $_jsonAttributes = [];

    /**
     * Автоматически добавляет поведение для JSON-полей,
     * если модель реализует метод getJsonAttributes()
     *
     * @return array
     */
    public function behaviors()
    {
        $behaviors = method_exists(get_parent_class($this), 'behaviors') ? parent::behaviors() : [];

        if (method_exists($this, 'getJsonAttributes')) {
            $jsonAttrs = $this->getJsonAttributes();
            if (is_array($jsonAttrs)) {
                $behaviors = array_merge($behaviors, $this->jsonAttributeBehaviors($jsonAttrs));
            }
        }

        return $behaviors;
    }

    /**
     * Создаёт поведение AttributeBehavior для каждого JSON-поля
     * для автоматического кодирования при сохранении и декодирования при загрузке.
     *
     * @param string[] $attributes Список JSON-полей
     * @return array Поведения для Yii2
     */
    public function jsonAttributeBehaviors(array $attributes): array
    {
        $this->_jsonAttributes = $attributes;

        $behaviors = [];
        foreach ($attributes as $attr) {
            $behaviors["json_{$attr}"] = [
                'class' => AttributeBehavior::class,
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => $attr,
                    BaseActiveRecord::EVENT_BEFORE_UPDATE => $attr,
                    BaseActiveRecord::EVENT_AFTER_FIND => $attr,
                ],
                'value' => function ($event) use ($attr) {
                    $model = $event->sender;
                    if ($event->name === BaseActiveRecord::EVENT_AFTER_FIND) {
                        $value = $this->getJsonAttributeValue($attr);
                        return is_string($value) ? Json::decode($value, true) : $value;
                    }
                    $value = $this->$attr;
                    return is_array($value) ? Json::encode($value) : $value;
                },
            ];
        }
        return $behaviors;
    }

    /**
     * Получить значение JSON-атрибута из модели (с учётом ActiveRecord или Model)
     *
     * @param string $name Имя атрибута
     * @return mixed|null
     */
    protected function getJsonAttributeValue(string $name)
    {
        return method_exists($this, 'getAttribute') ? $this->getAttribute($name) : ($this->$name ?? null);
    }

    /**
     * Установить значение JSON-атрибута в модель
     *
     * @param string $name Имя атрибута
     * @param mixed $value
     * @return void
     */
    protected function setJsonAttributeValue(string $name, $value): void
    {
        if (method_exists($this, 'setAttribute')) {
            $this->setAttribute($name, $value);
        } else {
            $this->$name = $value;
        }
    }

    /**
     * Получить вложенное значение из JSON-поля по пути.
     *
     * @param string $attr Название JSON-поля (должно быть в getJsonAttributes)
     * @param array|string|null $path Путь к значению. Если null — возвращается всё поле.
     *   Можно передать 'a.b.c' или ['a', 'b', 'c'].
     * @param mixed $default Значение по умолчанию, если путь не найден
     * @return mixed
     * @throws \InvalidArgumentException если $attr не в списке JSON-полей
     *
     * @example
     * ```php
     * $val = $model->getJsonAttr('settings', 'notifications.email', false);
     * ```
     */
    public function getJsonAttr(string $attr, $path = null, $default = null)
    {
        if (!in_array($attr, $this->_jsonAttributes ?? [], true)) {
            throw new \InvalidArgumentException("Unknown JSON attribute: {$attr}");
        }

        $data = $this->$attr ?? [];

        if ($path === null) {
            return $data;
        }

        return $this->getFromArrayPath($data, $path) ?? $default;
    }

    /**
     * Установить значение во вложенное поле JSON по пути.
     *
     * @param string $attr Название JSON-поля
     * @param array|string $path Путь к значению, например 'a.b.c' или ['a','b','c']
     * @param mixed $value Значение для установки
     * @throws \InvalidArgumentException если $attr не в списке JSON-полей
     *
     * @example
     * ```php
     * $model->setJsonAttr('settings', ['notifications', 'email'], true);
     * $model->setJsonAttr('settings', 'notifications.sms', false);
     * ```
     */
    public function setJsonAttr(string $attr, $path, $value): void
    {
        if (!in_array($attr, $this->_jsonAttributes ?? [], true)) {
            throw new \InvalidArgumentException("Unknown JSON attribute: {$attr}");
        }

        $data = $this->$attr ?? [];
        $this->setInArrayPath($data, $path, $value);
        $this->$attr = $data;
    }

    /**
     * Получить значение из массива по пути.
     *
     * @param array $data Массив данных
     * @param array|string $path Путь (массив ключей или строка с точками)
     * @return mixed|null
     */
    protected function getFromArrayPath(array $data, $path)
    {
        if (is_string($path)) {
            $path = explode('.', $path);
        }

        foreach ($path as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }
        return $data;
    }

    /**
     * Установить значение в массив по пути.
     *
     * @param array $data Массив данных (передаётся по ссылке)
     * @param array|string $path Путь (массив ключей или строка с точками)
     * @param mixed $value Значение для установки
     * @return void
     */
    protected function setInArrayPath(array &$data, $path, $value): void
    {
        if (is_string($path)) {
            $path = explode('.', $path);
        }

        $ref = &$data;
        foreach ($path as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }

        $ref = $value;
    }

    /**
     * Рекурсивно объединяет $default в $base без перезаписи не-null значений.
     * Если значение в $base отсутствует или равно null — подставляется из $default.
     *
     * @param array $base Текущий массив
     * @param array $default Массив значений по умолчанию
     * @return array Объединённый массив
     */
    protected function arrayDeepMerge(array $base, array $default): array
    {
        foreach ($default as $key => $value) {
            if (is_array($value)) {
                $baseValue = $base[$key] ?? [];
                if (!is_array($baseValue)) {
                    $baseValue = [];
                }
                $base[$key] = $this->arrayDeepMerge($baseValue, $value);
            } else {
                if (!isset($base[$key]) || $base[$key] === null) {
                    $base[$key] = $value;
                }
            }
        }
        return $base;
    }

    /**
     * Преобразует defaults из формата
     * ```
     * [
     *    'proxy.enabled' => false,
     *    ['limits','daily'] => 100,
     * ]
     * ```
     * в вложенный массив:
     * ```
     * [
     *    'proxy' => ['enabled' => false],
     *    'limits' => ['daily' => 100],
     * ]
     * ```
     *
     * @param array $defaults
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function convertDefaultsToNestedArray(array $defaults): array
    {
        $result = [];
        foreach ($defaults as $path => $value) {
            if (is_array($path)) {
                $keys = $path;
            } elseif (is_string($path)) {
                $keys = explode('.', $path);
            } else {
                throw new \InvalidArgumentException('Invalid key path type in defaults.');
            }

            $ref = &$result;
            foreach ($keys as $key) {
                if (!isset($ref[$key]) || !is_array($ref[$key])) {
                    $ref[$key] = [];
                }
                $ref = &$ref[$key];
            }

            $ref = $value;
        }
        return $result;
    }
}
