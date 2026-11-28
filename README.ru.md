# Componenta Policy

`componenta/policy` — ядро авторизации Componenta. Приложение передаёт идентификатор действия, объект актора и при необходимости контекст; `PolicyInterface` принимает решение о доступе.

[English documentation](README.md)

## Установка

```bash
composer require componenta/policy
```

Пакет требует PHP 8.5.13+ и интегрируется с Componenta Config 4, DI 6, Identity 1.1 и PSR-11 контейнерами. Установка ветки разработки с ещё не выпущенными зависимостями описана в [инструкции по интеграции](docs/development.md).

## Базовая модель

```php
interface PolicyInterface
{
    public function enforce(
        object $actor,
        ContextInterface $context,
    ): true|DenyReason;
}
```

Политика возвращает `true`, когда доступ разрешён, и `DenyReason`, когда правило корректно вычислено, но доступ запрещён. Неподходящий тип актора, отсутствие обязательного контекста или ошибочная конфигурация являются ошибками политики и остаются исключениями.

Универсального составного интерфейса актора нет. `PolicyEnforcer` принимает `object`, а каждая политика проверяет только нужные ей возможности, например:

- `IdentityInterface` для идентичности и владения;
- `PermissionAwareInterface` для прямых разрешений;
- `RoleAwareInterface` для одной роли;
- `RoleCollectionAwareInterface` для коллекции ролей, принадлежащей актору;
- `RoleInterface` или `RoleCollectionInterface` напрямую там, где role-policy принимает сами объекты ролей;
- прикладные capability-интерфейсы для пользовательских политик.

`RoleInterface` предоставляет стабильное имя через read-only свойство `public string $name`. `RoleCollection` и встроенные role-policy работают именно с этим свойством.

## Семантика актора

Для интеграций используются два контракта:

```php
interface ActorAwareInterface
{
    public object $actor { get; }
}

interface ActorProviderInterface
{
    public function getActor(): ?object;
}
```

`ActorAwareInterface` предназначен для сообщений и других объектов, которые явно несут субъект политики.

`ActorProviderInterface` получает субъекта из конкретного источника интеграции, например HTTP-запроса, сессии или токена аутентификации. Возвращаемые значения имеют разные значения:

```text
конкретный object -> актор найден
Guest             -> явно заданный анонимный актор
null              -> актор не найден
```

Слой policy никогда сам не превращает `null` в `Guest`. Интеграция, которая хочет представить анонимный доступ как субъект, должна вернуть `Guest` явно. Стандартный `GuestActorProvider` именно так и работает.

`Guest` — полноценный анонимный актор. Встроенные защищённые политики корректно возвращают для него обычный отказ. Произвольный объект, который не реализует требуемую политикой capability, остаётся ошибкой интеграции/конфигурации и приводит к `InvalidPolicyActorException`.

## PolicyEnforcer

`PolicyEnforcer` получает политику через `PolicyProviderInterface`, создаёт или нормализует контекст и выполняет проверку.

```php
$result = $enforcer->check('posts.update', $user, [
    'resource' => $post,
]);

$allowed = $enforcer->can('posts.update', $user, [
    'resource' => $post,
]);

$enforcer->enforce('posts.update', $user, [
    'resource' => $post,
]);
```

- `check()` возвращает `true|AccessDenied` для обычного результата авторизации;
- `can()` возвращает `bool`;
- `enforce()` бросает `AccessDeniedException` при обычном отказе;
- ошибки использования политики и конфигурации не преобразуются в отказ доступа.

Отсутствующая политика обрабатывается через `MissingPolicyBehavior`. По умолчанию используется `DENY`. Для отдельного вызова поведение можно переопределить через `PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR` в контексте.

## Встроенные политики

| Политика | Правило |
|---|---|
| `Allow` | Всегда разрешает. |
| `Deny` | Всегда запрещает. |
| `CallbackPolicy` | Вычисляет правило по текущему актору и контексту. |
| `PermissionPolicy` | Требует все или любое из заданных разрешений. |
| `RolePolicy` | Требует одну из разрешённых ролей. |
| `OwnerPolicy` | Требует, чтобы актор с `IdentityInterface` владел ресурсом из контекста. |
| `HierarchyPolicy` | Требует, чтобы роль актора превосходила роль/роли цели. |
| `AllOf` | Все вложенные политики должны разрешить действие. |
| `OneOf` | Достаточно одной разрешающей политики. |

Для `PermissionPolicy`, `RolePolicy`, `OwnerPolicy` и `HierarchyPolicy` `Guest` является корректным субъектом и получает обычный `DenyReason`. Неизвестный non-guest объект без требуемой capability остаётся ошибкой. Инварианты контекста также fail-fast: например, `OwnerPolicy` всё равно требует корректный `resource`, даже если актор — `Guest`.

`RolePolicy` и `HierarchyPolicy` принимают `RoleInterface` и `RoleCollectionInterface` напрямую, а также role-aware capability акторов. Если актор реализует оба интерфейса ролей, обе политики учитывают и одиночную роль, и коллекцию. Пустая коллекция ролей является корректным источником ролей и при требовании роли приводит к обычному отказу, а не к `InvalidPolicyActorException`.

### Пример разрешений

```php
use Componenta\Policy\Actor\PermissionAwareInterface;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Permission\PermissionCollectionInterface;
use Componenta\Policy\Permission\PermissionInterface;
use Componenta\Policy\Policies\PermissionPolicy;

enum PostPermission: string implements PermissionInterface
{
    case CREATE = 'posts.create';

    public function getName(): string
    {
        return $this->value;
    }
}

final readonly class User implements PermissionAwareInterface
{
    public function __construct(
        public PermissionCollectionInterface $permissions,
    ) {}
}

$user = new User(new PermissionCollection([PostPermission::CREATE]));
$policy = new PermissionPolicy(PostPermission::CREATE);
```

`PermissionPolicy` объединяет разрешения, полученные напрямую от актора, из одной роли, через `RoleCollectionAwareInterface` или из переданного напрямую `RoleCollectionInterface`. Пустая коллекция ролей остаётся корректным источником разрешений и даёт обычный отказ из-за отсутствующего permission, а не `InvalidPolicyActorException`.

## Контекст

`ContextInterface` — неизменяемый набор атрибутов одной проверки. Массивы, переданные в `PolicyEnforcer`, преобразуются через `ContextFactoryInterface`.

Политики с обязательным контекстом проверяют его явно:

- отсутствующий обязательный атрибут -> `MissingPolicyContextAttributeException`;
- неверный тип атрибута -> `InvalidPolicyContextAttributeException`.

`OwnerPolicy` использует `resource`, `HierarchyPolicy` — `target`.

## Провайдеры политик

`PolicyProviderInterface` находит политику по action id. В пакет входят:

- `ArrayPolicyProvider` — карта action id с ленивыми фабриками;
- `AttributePolicyProvider` — политики из PHP-атрибутов;
- `CompositePolicyProvider` — первый найденный вариант;
- `AllOfPolicyProvider` — объединяет политики из нескольких источников через `AllOf`;
- `OneOfPolicyProvider` — объединяет найденные политики через `OneOf`.

Стандартный `PolicyProviderFactory` последовательно объединяет настроенную карту политик, пользовательские провайдеры и нативные атрибуты. Каждый вызов `provideFor()` заново создаёт атрибутные политики через нативный механизм PHP и текущую DI-фабрику. Фабрики из карты также вызываются при каждом разрешении. Явно зарегистрированные объекты политик сохраняют заданное приложением время жизни. Ошибки конструкторов и фабрик распространяются при том вызове, где они возникли. Фабрики, которым нужна конфигурация, получают `Componenta\Config\ContainerValue`.

Дисковая компиляция политик удалена. Удалите настройки `compiled_policies`, `compiled_policies_file`, `compiled_policies_strict` и зависимость `componenta/policy-app`. Регистрируйте ConfigProvider этого пакета напрямую. Старые артефакты не используются; билдер приложения для policy не требуется.

Классы пользовательских policy providers и shape configured policy map валидируются до использования. Ленивая фабрика configured policy обязана вернуть `PolicyInterface`.

## Атрибуты

Политики, аргументы которых допустимы в PHP attributes, можно использовать напрямую:

```php
#[RolePolicy('admin')]
#[PermissionPolicy([PostPermission::CREATE], ContainsMode::ALL)]
final class PostController {}
```

Если политика требует DI-сервисы, используется `Componenta\Policy\Attribute\Policy`, а `AttributePolicyProvider` создаёт объект через `Componenta\DI\FactoryInterface`.

`Componenta\Policy\Attribute\AllOf` и `Componenta\Policy\Attribute\OneOf` позволяют составлять композиции из прямых политик и ссылок на политики.

### Callback-политики

`CallbackPolicy` подходит для небольшого правила над актором и контекстом, уже доступными слою авторизации:

```php
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Policies\CallbackPolicy;
use Componenta\Policy\Policies\RolePolicy;

#[RolePolicy('editor')]
#[CallbackPolicy(static function (object $actor, ContextInterface $context): true|DenyReason {
    return $context->getAttribute('editingClosed', false) === true
        ? new DenyReason('Редактирование закрыто.')
        : true;
})]
final class EditPost {}
```

Для именованной функции доступен нативный first-class callable, в том числе закрытый статический метод объявляющего класса: `#[CallbackPolicy(self::decide(...))]`. PHP 8.5.13+ обеспечивает корректные область класса и видимость, когда такой callable объявлен в общем trait. В аргументах атрибута используйте `static function (...) { ... }` или first-class callable: стрелочные функции и захват внешних переменных в этих константных выражениях недопустимы.

При каждом `enforce()` callback получает ровно `(object $actor, ContextInterface $context)`. Можно указать конкретный тип актора в callback; статический анализ сохраняет этот тип. Несовместимый актор вызывает нативный `TypeError`, обёрнутый в ошибку callback-политики. Допустимы только `true` или `DenyReason`; переданный объект причины сохраняется без изменений. `false`, `null` и другие результаты являются ошибками контракта. Контейнер и сервисы автоматически не передаются. Если правило требует зависимостей, используйте класс политики через `#[Policy(...)]`.

`CallbackPolicyException` обозначает недопустимый результат или стороннее исключение callback. Исходное исключение, включая нативную ошибку сигнатуры, доступно через `getPrevious()`. Исключения, реализующие `PolicyExceptionInterface`, распространяются без изменений. `can()` не скрывает ошибки вычисления, а `OneOf` не превращает их в обычный отказ.

Конструктор сохраняет замыкание без вызова. Решения, актор и контекст не мемоизируются. Повторные атрибуты сохраняют существующую семантику AND; `AllOf` и `OneOf` сохраняют порядок и короткое замыкание. Замыкания, созданные обычным PHP-кодом, сохраняют своё нативное время жизни и захваченное приложением состояние.

## Action id

`PolicyEnforcer` получает строковый action id явно. Интеграции могут использовать `ActionIdAwareInterface`:

```php
interface ActionIdAwareInterface
{
    public string $actionId { get; }
}
```

Например, CQRS-интеграция использует это свойство, если оно присутствует, иначе берёт имя класса сообщения.

## Конфигурация

Зарегистрируйте:

```php
return [
    new Componenta\Policy\ConfigProvider(),
];
```

Провайдер регистрирует `PolicyEnforcer`, `PolicyProviderInterface`, `ContextFactoryInterface` и стандартный `ActorProviderInterface`, возвращающий `Guest`.

Основные ключи:

- `ConfigKey::POLICY`;
- `ConfigKey::POLICIES`;
- `ConfigKey::PROVIDERS`;
- `ConfigKey::MISSING_POLICY_BEHAVIOR`.

## Границы интеграции

`componenta/policy` отвечает только за семантику авторизации. Получение и транспорт актора принадлежат интеграционным пакетам:

- `componenta/cqrs-policy` связывает actor semantics команд/запросов с policy checks;
- `componenta/di` может внедрять текущего пользователя и создавать политики;
- `componenta/identity` предоставляет UUID identity contracts.

Анонимный доступ представлен `Guest`, а не `null`. `null` остаётся явным сигналом интеграции, что актор не был найден.
