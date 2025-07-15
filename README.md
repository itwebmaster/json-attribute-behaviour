# JsonAttributeBehaviorTrait

Удобный трейт для работы с JSON-полями в моделях Yii2.

## Возможности

- Автоматическое кодирование и декодирование JSON в полях ActiveRecord или Model
- Удобный доступ к вложенным элементам JSON с помощью путей (строкой с точками или массивом ключей)
- Легкое изменение вложенных значений
- Утилиты для глубокого слияния массивов и конвертации плоских настроек в вложенные структуры

## Установка

```bash
composer require itwebmaster/json-attribute-behavior
```

### Использование

Добавьте трейт в свою модель и определите метод getJsonAttributes():

```php
use itwebmaster\JsonAttributeBehavior\JsonAttributeBehaviorTrait;

class MyModel extends \yii\db\ActiveRecord
{
    use JsonAttributeBehaviorTrait;

    public function getJsonAttributes(): array
    {
        return ['settings', 'options'];
    }
}
```

Теперь ваши поля settings и options будут автоматически кодироваться/декодироваться в JSON при сохранении и загрузке.

### Основные методы

- getJsonAttr(string $attr, array|string|null $path = null, $default = null) — получить значение из JSON по пути

- setJsonAttr(string $attr, array|string $path, $value) — установить значение по пути в JSON

Пример:

```php
$model->setJsonAttr('settings', 'notifications.email', true);
$emailEnabled = $model->getJsonAttr('settings', ['notifications', 'email'], false);
```
---

Если хочешь, могу помочь добавить в трейт и валидацию JSON по схеме и другие твои пожелания.