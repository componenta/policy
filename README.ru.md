# Componenta Policy

`componenta/policy` — библиотека авторизации на основе политик. Она отделяет правила доступа от контроллеров, обработчиков команд и другого прикладного кода: приложение передает идентификатор действия, актора и контекст, а библиотека находит подходящую политику и возвращает решение.

Пакет содержит контракты, встроенные политики, провайдеры политик, `PolicyEnforcer`, PHP-атрибуты и интеграцию с конфигурацией. Обнаружение атрибутов при прогреве кеша и компиляция карт политик находятся в [`componenta/policy-app`](../policy-app/README.ru.md). Вызов политик из команд и запросов обычно делает [`componenta/cqrs`](../cqrs/README.ru.md).

**[Документация на английском](README.md)**

## Установка

```bash
composer require componenta/policy
```

## Зависимости

| Зависимость | Зачем нужна |
|---|---|
| PHP `^8.4` | Используются типизированные свойства с хуками, enum и строгая типизация. |
| `componenta/config` | Подключает `ConfigProvider` и читает настройки `policy`. |
| `componenta/di` | Создает политики из `#[Policy]`, когда им нужны сервисы контейнера. |
| `componenta/identity` | Дает `IdentityInterface` для акторов и владельцев ресурсов. |
| `psr/container` | Используется провайдерами и фабриками политик. |

## Основные понятия

| Понятие | Что означает |
|---|---|
| Актор | Пользователь, системный субъект или другой объект, от имени которого выполняется действие. |
| Действие | Строковый идентификатор операции, например `posts.create` или `App\Controller\PostController::update`. |
| Политика | Объект `PolicyInterface`, который решает, разрешено ли действие конкретному актору в конкретном контексте. |
| Контекст | Иммутабельный набор данных для проверки: ресурс, целевой пользователь, параметры запроса. |
| Провайдер политик | Объект `PolicyProviderInterface`, который находит политику по идентификатору действия. |
| Отказ | Валидный результат проверки, когда актор и контекст корректны, но правило доступа запрещает действие. |
| Ошибка политики | Некорректное использование или настройка: неподходящий актор, отсутствующий ресурс в контексте, неправильный тип данных. |

`PolicyEnforcer` принимает любого актора как `object`. Конкретная политика сама проверяет, какие способности ей нужны: набор разрешений, роль, UUID-идентичность или владение ресурсом.

## Быстрый старт

Минимальный пример: создать разрешение, актора с этим разрешением, зарегистрировать политику и проверить действие.

```php
use Componenta\Policy\Actor\PermissionAwareInterface;
use Componenta\Policy\Permission\PermissionCollection;
use Componenta\Policy\Permission\PermissionCollectionInterface;
use Componenta\Policy\Permission\PermissionInterface;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\PolicyEnforcer;
use Componenta\Policy\Provider\ArrayPolicyProvider;

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

$provider = new ArrayPolicyProvider($container, [
    'posts.create' => static fn () => new PermissionPolicy(PostPermission::CREATE),
]);

$enforcer = new PolicyEnforcer($provider);

$enforcer->can('posts.create', $user);     // true
$enforcer->enforce('posts.create', $user); // ничего не выбрасывает
```

`$container` — любой PSR-11 контейнер. `ArrayPolicyProvider` передает его фабрикам `callable`, чтобы политики можно было создавать лениво.

## Конфигурация

Подключите провайдер пакета в конфигурации приложения:

```php
return [
    new Componenta\Policy\ConfigProvider(),
];
```

`ConfigProvider` регистрирует:

| Сервис | Что делает |
|---|---|
| `PolicyEnforcer` | Основная точка входа для проверки доступа. |
| `PolicyProviderInterface` | Финальный провайдер политик, собранный из конфигурации, скомпилированных карт и атрибутов. |
| `ContextFactoryInterface` | Создает `ContextInterface` для конкретного действия из массива атрибутов. |
| `ActorProviderInterface` | По умолчанию возвращает гостевого актора. Интеграции могут заменить сервис на провайдер текущего пользователя. |

Пример конфигурации:

```php
use Componenta\Policy\ConfigKey;
use Componenta\Policy\MissingPolicyBehavior;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;

return [
    ConfigKey::POLICY => [
        ConfigKey::POLICIES => [
            'posts.create' => static fn () => new PermissionPolicy(PostPermission::CREATE),
            'admin.access' => static fn () => new RolePolicy('admin'),
        ],
        ConfigKey::PROVIDERS => [
            AppPolicyProvider::class,
        ],
        ConfigKey::MISSING_POLICY_BEHAVIOR => MissingPolicyBehavior::DENY,
    ],
];
```

Ключи конфигурации:

| Ключ | Значение |
|---|---|
| `ConfigKey::POLICY` | Корневой раздел настроек политики. |
| `ConfigKey::POLICIES` | Карта `actionId => PolicyInterface|callable`. Используется `ArrayPolicyProvider`. |
| `ConfigKey::PROVIDERS` | Список классов дополнительных `PolicyProviderInterface`, получаемых из контейнера. |
| `ConfigKey::MISSING_POLICY_BEHAVIOR` | Поведение при отсутствии политики: `DENY` или `ALLOW`. |
| `ConfigKey::COMPILED_POLICIES` | Скомпилированная карта политик из `componenta/policy-app`. |
| `ConfigKey::COMPILED_POLICIES_FILE` | Файл со скомпилированной картой политик. |
| `ConfigKey::COMPILED_POLICIES_STRICT` | Выбрасывать исключение для невалидных скомпилированных дескрипторов вместо резервного поиска по атрибутам. |

`PolicyProviderFactory` собирает провайдеры в таком порядке: карта из `ConfigKey::POLICIES`, пользовательские провайдеры, скомпилированные политики, затем `AttributePolicyProvider` как резервный провайдер. Один провайдер возвращается напрямую, несколько оборачиваются в `CompositePolicyProvider`.

Стандартная фабрика использует поведение «первая найденная политика». Если приложению нужно объединять политики из нескольких источников через `AllOfPolicyProvider` или `OneOfPolicyProvider`, зарегистрируйте собственную реализацию `PolicyProviderInterface` вместо стандартной фабрики или соберите нужный композит внутри одного пользовательского провайдера.

Интеграции могут задавать идентификатор действия через `ActionIdAwareInterface`:

```php
use Componenta\Policy\ActionIdAwareInterface;

final readonly class PublishPostCommand implements ActionIdAwareInterface
{
    public function __construct(
        public string $actionId = 'posts.publish',
    ) {}
}
```

Сам `PolicyEnforcer` принимает строковый `actionId` явно. `ActionIdAwareInterface` нужен внешним слоям, например [`componenta/cqrs`](../cqrs/README.ru.md): стандартный резолвер CQRS берет `$object->actionId`, если объект реализует интерфейс, иначе использует имя класса.

## PolicyEnforcer

`PolicyEnforcer` не содержит правил доступа. Он получает политику из `PolicyProviderInterface`, создает контекст и вызывает `PolicyInterface::enforce()`.

```php
$result = $enforcer->check('posts.update', $user, ['resource' => $post]);

if ($result !== true) {
    $logger->warning('Access denied', [
        'action' => $result->actionId,
        'reason' => $result->reason->value,
        'policy' => $result->reason->policyClass,
    ]);
}

$allowed = $enforcer->can('posts.update', $user, ['resource' => $post]);

$enforcer->enforce('posts.update', $user, ['resource' => $post]);
```

Методы:

`check(string $actionId, object $actor, ContextInterface|array $context = []): true|AccessDenied`

Основной метод проверки. Он возвращает подробный результат и не выбрасывает исключение при обычном отказе доступа.

Поведение `check()`:

- если `$context` передан массивом, он преобразуется в `ContextInterface` через `ContextFactoryInterface::create($actionId, $context)`;
- если в контексте есть `PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR` со значением `MissingPolicyBehavior`, это значение применяется только для текущего вызова и удаляется из контекста перед вызовом политики;
- если `PolicyProviderInterface` не нашел политику и действует `MissingPolicyBehavior::ALLOW`, метод возвращает `true`;
- если политика не найдена и действует `MissingPolicyBehavior::DENY`, метод возвращает `AccessDenied`;
- если политика вернула `true`, метод возвращает `true`;
- если политика вернула `DenyReason`, метод оборачивает его в `AccessDenied` с `actionId`, актором и контекстом;
- если политика не может быть корректно выполнена из-за неправильного актора, контекста или конфигурации, исключение политики не перехватывается.

`can(string $actionId, object $actor, ContextInterface|array $context = []): bool`

Короткая булева обертка над `check()`. Возвращает `true` только если действие разрешено. Обычный отказ превращается в `false`, но исключения политики не подавляются.

`enforce(string $actionId, object $actor, ContextInterface|array $context = []): void`

Строгая проверка. Ничего не возвращает при успехе и выбрасывает `AccessDeniedException`, если `check()` вернул отказ. Исключения политики не оборачиваются в `AccessDeniedException`.

`withProvider()`, `withFactory()`, `withBehavior()`

Возвращают новый `PolicyEnforcer` с замененным провайдером политик, фабрикой контекста или поведением при отсутствии политики. Исходный объект не меняется.

Ошибки уровня политики не превращаются в отказ доступа. Например, если `OwnerPolicy` требует ресурс в контексте, а ресурс не передан, будет выброшено исключение политики. Это сигнал о неправильном вызове или настройке.

## Поведение при отсутствии политики

По умолчанию используется `MissingPolicyBehavior::DENY`: если для действия не найдена политика, доступ запрещается. Это безопасное поведение для приложения.

```php
use Componenta\Policy\MissingPolicyBehavior;

$enforcer = new PolicyEnforcer($provider, behavior: MissingPolicyBehavior::DENY);
```

Для одного вызова поведение можно переопределить через контекст:

```php
use Componenta\Policy\PolicyEnforcer;
use Componenta\Policy\MissingPolicyBehavior;

$enforcer->check('health.read', $user, [
    PolicyEnforcer::ATTR_MISSING_POLICY_BEHAVIOR => MissingPolicyBehavior::ALLOW,
]);
```

Значения другого типа игнорируются, и продолжает действовать настройка `PolicyEnforcer`.

## Акторы, роли и разрешения

Встроенные политики зависят от маленьких интерфейсов. Модель пользователя реализует только те способности, которые реально нужны приложению.

```php
interface PermissionAwareInterface
{
    public PermissionCollectionInterface $permissions { get; }
}

interface RoleAwareInterface
{
    public RoleInterface $role { get; }
}

interface RoleInterface extends PermissionAwareInterface
{
    public string $name { get; }
    public function outranks(RoleAwareInterface|RoleInterface $other): bool;
}

interface RoleCollectionAwareInterface
{
    public RoleCollectionInterface $roles { get; }
}

interface RoleCollectionInterface extends IteratorAggregate, Countable
{
    public function contains(
        RoleInterface|RoleCollectionInterface|string $role,
        ContainsMode $mode = ContainsMode::ANY,
    ): bool;
}
```

`PermissionPolicy` умеет читать разрешения напрямую из `PermissionAwareInterface`, из одной роли через `RoleAwareInterface` и из коллекции ролей через `RoleCollectionAwareInterface`. Источники объединяются в эффективный `PermissionCollection`: наличие разрешения в любом источнике достаточно.

Для промежуточных слоев и других интеграций есть два дополнительных контракта:

```php
interface ActorAwareInterface
{
    public ActorInterface $actor { get; }
}

interface ActorProviderInterface
{
    public function getActor(): ?object;
}
```

`ActorAwareInterface` подходит для команд или запросов, которые уже несут актора внутри себя. `ActorProviderInterface` нужен, когда текущий пользователь берется из внешнего окружения: HTTP-запроса, сессии, токена или процесса воркера. `getActor()` может вернуть `null` для анонимного доступа; как именно это обрабатывается, решает интеграционный слой. Встроенные политики все равно проверяют нужный им интерфейс актора самостоятельно.

Разрешение — любой объект `PermissionInterface`:

```php
use Componenta\Policy\Permission\PermissionInterface;

enum PostPermission: string implements PermissionInterface
{
    case CREATE = 'posts.create';
    case EDIT_ANY = 'posts.edit.any';

    public function getName(): string
    {
        return $this->value;
    }
}
```

`PermissionCollectionInterface` — контракт только для чтения: `contains()`, `toArray()`, итерация и `count()`. Метод `contains()` принимает `ContainsMode::ANY` и `ContainsMode::ALL` при сравнении с другой коллекцией. Конкретный `PermissionCollection` дополнительно дает `add()` и `remove()` для инфраструктуры, сидеров и фикстур.

```php
$permissions = new PermissionCollection([PostPermission::CREATE]);

$permissions->contains('posts.create');        // true
$permissions->contains(PostPermission::CREATE); // true
$permissions->add(PostPermission::EDIT_ANY);
$permissions->remove('posts.create');
```

## Контекст

`ContextInterface` — иммутабельное хранилище пар ключ-значение для одной проверки авторизации. Интерфейс расширяет `Componenta\Arrayable\Arrayable`, поэтому полная карта атрибутов доступна через `toArray()`. Массив, переданный в `PolicyEnforcer`, автоматически преобразуется через `ContextFactoryInterface`.

Фабрика контекста получает идентификатор действия и исходные атрибуты:

```php
interface ContextFactoryInterface
{
    public function create(string $actionId, array $attributes = []): ContextInterface;
}
```

Обычная реализация просто создает `Context`, но свою фабрику можно использовать, чтобы добавлять данные по конкретным действиям, например ресурс для `posts.update`.

```php
use Componenta\Policy\Context\Context;

$context = new Context(['resource' => $post]);
$context = $context->withAttribute('ip', '127.0.0.1');

if (!$context->hasAttribute('resource')) {
    // выбросьте исключение политики из конкретной политики
}

$resource = $context->getAttribute('resource');
$ip = $context->getAttribute('ip');
```

Политики явно валидируют обязательные атрибуты контекста. Встроенные политики бросают `MissingPolicyContextAttributeException`, если атрибута нет, и `InvalidPolicyContextAttributeException`, если форма атрибута не подходит.

## Встроенные политики

| Политика | Что проверяет | Что требуется |
|---|---|---|
| `PermissionPolicy` | У актора есть все или любое из указанных разрешений. | `PermissionAwareInterface`, `RoleAwareInterface` и/или `RoleCollectionAwareInterface`. |
| `RolePolicy` | Любая роль актора входит в список разрешенных ролей. | `RoleAwareInterface`, `RoleCollectionAwareInterface`, `RoleInterface` или `RoleCollectionInterface`. |
| `HierarchyPolicy` | Одна из ролей актора выше каждой роли цели. | Актор и `target` раскрывают роль или коллекцию ролей; `target` берется из контекста. |
| `OwnerPolicy` | Актор владеет ресурсом. | Актор `IdentityInterface`, ресурс `OwnableInterface` в контексте под ключом `resource`. |
| `Allow` | Всегда разрешает доступ. | Ничего. |
| `Deny` | Всегда запрещает доступ с указанной причиной. | Ничего. |
| `AllOf` | Все вложенные политики должны разрешить действие. | Ничего сверх вложенных политик. |
| `OneOf` | Хотя бы одна вложенная политика должна разрешить действие. | Ничего сверх вложенных политик. |

### Собственная политика

Политика реализует `PolicyInterface`. Наследование от `AbstractPolicy` необязательно, но удобно из-за `deny()` и `extractRole()`.

```php
use Componenta\Policy\Context\ContextInterface;
use Componenta\Policy\Exception\DenyReason;
use Componenta\Policy\Exception\InvalidPolicyActorException;
use Componenta\Policy\Policies\AbstractPolicy;

interface AgeAwareInterface
{
    public int $age { get; }
}

final class MinimumAgePolicy extends AbstractPolicy
{
    public function __construct(
        private readonly int $minimumAge,
    ) {}

    public function enforce(object $actor, ContextInterface $context): true|DenyReason
    {
        if (!$actor instanceof AgeAwareInterface) {
            throw InvalidPolicyActorException::expected(
                actor: $actor,
                expectedType: AgeAwareInterface::class,
            );
        }

        if ($actor->age < $this->minimumAge) {
            return $this->deny("Требуется возраст не менее {$this->minimumAge} лет");
        }

        return true;
    }
}
```

Возвращайте `DenyReason`, когда правило доступа корректно проверено и доступ запрещен. Выбрасывайте исключение, когда политику невозможно корректно оценить из-за неправильного актора, контекста или конфигурации.

## Провайдеры политик

### ArrayPolicyProvider

`ArrayPolicyProvider` связывает `actionId` с готовой политикой или фабрикой `callable`. Фабрика получает PSR-11 контейнер, создается лениво и кешируется по ключу действия.

```php
use Componenta\Policy\Provider\ArrayPolicyProvider;
use Componenta\Policy\Policies\OneOf;
use Componenta\Policy\Policies\OwnerPolicy;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;

$provider = new ArrayPolicyProvider($container, [
    'posts.create' => static fn () => new PermissionPolicy(PostPermission::CREATE),
    'posts.delete' => static fn () => OneOf::of([
        new RolePolicy('admin'),
        new OwnerPolicy(),
    ]),
]);
```

### AttributePolicyProvider

`AttributePolicyProvider` читает политики из PHP-атрибутов.

```php
use Componenta\Policy\Provider\AttributePolicyProvider;

$attributeProvider = new AttributePolicyProvider($factory);
```

`$factory` — это `Componenta\DI\FactoryInterface`; он нужен провайдеру, чтобы создавать политики из `#[Policy(...)]` и доменных атрибутов-наследников. Если атрибуты используют только прямые политики вроде `#[PermissionPolicy]`, фабрика все равно передается в конструктор, потому что провайдер поддерживает оба режима.

Формат `actionId`:

| Формат | Что читается |
|---|---|
| `App\Controller\PostController::update` | Атрибуты метода. Атрибуты метода родителя видны, если метод не переопределен. |
| `App\Controller\AdminController` | Атрибуты класса и всех родителей. Атрибуты потомка идут раньше унаследованных. |

Несколько атрибутов на одном месте объединяются через `AllOf`. Атрибуты класса не подмешиваются в поиск политики по методу.

```php
use Componenta\Policy\Policies\OwnerPolicy;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;

#[RolePolicy('admin')]
abstract class BaseAdminController {}

final class PostController extends BaseAdminController
{
    #[PermissionPolicy(PostPermission::EDIT_ANY)]
    #[OwnerPolicy]
    public function update(int $id): void {}
}

$enforcer->check(PostController::class . '::update', $user, ['resource' => $post]);
$enforcer->check(PostController::class, $user);
```

Первый вызов проверяет только политики метода: `PostPermission::EDIT_ANY` и владение ресурсом. Второй вызов проверяет политику класса `RolePolicy('admin')`, унаследованную от `BaseAdminController`.

### CompositePolicyProvider

`CompositePolicyProvider` перебирает провайдеры по порядку и возвращает первую найденную политику:

```php
$provider = new CompositePolicyProvider([$arrayProvider, $attributeProvider]);
```

`add()` добавляет провайдер в конец цепочки, `prepend()` — в начало. Эти методы предназначены для сборки приложения при старте; менять цепочку во время обработки запросов не стоит.

### AllOfPolicyProvider

`AllOfPolicyProvider` тоже перебирает несколько провайдеров, но не останавливается на первой найденной политике. Он собирает все найденные политики для одного `actionId` и применяет их через `AllOf`:

```php
use Componenta\Policy\Provider\AllOfPolicyProvider;

$provider = new AllOfPolicyProvider([$tenantProvider, $attributeProvider]);
```

Здесь `$attributeProvider` — это уже созданный `AttributePolicyProvider` из раздела выше, а `$tenantProvider` — провайдер приложения, который возвращает политику проверки арендатора для тех же `actionId`.

Поведение:

- если ни один провайдер не вернул политику, возвращается `null`;
- если найдена одна политика, она возвращается как есть;
- если найдено несколько политик, возвращается `AllOf::of($policies)`.

Используйте этот провайдер, когда правила из разных источников должны усиливать друг друга. Например: `AttributePolicyProvider` берет `#[PermissionPolicy]` с команды, а провайдер приложения добавляет проверку арендатора. Для сценария переопределения используйте `CompositePolicyProvider`.

`AllOfPolicyProvider` не подключается стандартной `PolicyProviderFactory` автоматически. Чтобы сделать его финальным провайдером приложения, замените сервис `PolicyProviderInterface` в контейнере или верните такой композит из собственной фабрики.

### OneOfPolicyProvider

`OneOfPolicyProvider` обходит несколько провайдеров, собирает все найденные политики для одного `actionId` и применяет их через `OneOf`:

```php
use Componenta\Policy\Provider\OneOfPolicyProvider;

$provider = new OneOfPolicyProvider([$ownerProvider, $attributeProvider]);
```

Здесь `$attributeProvider` — это уже созданный `AttributePolicyProvider` из раздела выше, а `$ownerProvider` — провайдер приложения, который возвращает политику владельца ресурса для тех же `actionId`.

Поведение:

- если ни один провайдер не вернул политику, возвращается `null`;
- если найдена одна политика, она возвращается как есть;
- если найдено несколько политик, возвращается `OneOf::of($policies)`.

Используйте этот провайдер, когда достаточно любого правила из разных источников. Например: один провайдер разрешает владельцу ресурса, а `AttributePolicyProvider` читает `#[PermissionPolicy]` для роли с расширенными правами. Не используйте его как безопасную замену `AllOfPolicyProvider`: `OneOfPolicyProvider` расширяет доступ.

`OneOfPolicyProvider` тоже не подключается стандартной `PolicyProviderFactory` автоматически. Подключайте его явно как финальный `PolicyProviderInterface`, когда именно такое расширение доступа является требованием приложения.

### CompiledPolicyProvider

`CompiledPolicyProvider` читает дескрипторы политик, сгенерированные `componenta/policy-app`. Он нужен для боевого окружения, где не хочется искать атрибуты через рефлексию на горячем пути. По умолчанию устаревшие или невалидные дескрипторы возвращают `null`, и следующий провайдер в цепочке может обработать действие. Установите `ConfigKey::COMPILED_POLICIES_STRICT` в `true`, чтобы вместо fallback получить `InvalidCompiledPolicyException`; это полезно, когда сломанный generated cache должен падать явно.

## Атрибуты

Политики, конструктор которых принимает только значения, допустимые в PHP-атрибутах, можно ставить напрямую:

```php
use Componenta\Policy\ContainsMode;
use Componenta\Policy\Policies\PermissionPolicy;
use Componenta\Policy\Policies\RolePolicy;

#[RolePolicy('editor')]
#[PermissionPolicy([PostPermission::CREATE, PostPermission::EDIT_ANY], ContainsMode::ANY)]
final class PostController {}
```

Когда политике нужны сервисы из контейнера, используйте `Componenta\Policy\Attribute\Policy`. Атрибут хранит класс политики и аргументы, а `AttributePolicyProvider` создает политику через `Componenta\DI\FactoryInterface`.

```php
use Componenta\Policy\Attribute\Policy;

#[Policy(PublishLimitPolicy::class, ['dailyLimit' => 10])]
public function store(): void {}
```

Для более чистого синтаксиса можно сделать доменный атрибут. Базовый `Policy` объявлен `readonly`, поэтому наследник тоже должен быть `readonly`.

```php
use Componenta\Policy\Attribute\Policy;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final readonly class PublishLimit extends Policy
{
    public function __construct(int $daily = 5)
    {
        parent::__construct(PublishLimitPolicy::class, ['dailyLimit' => $daily]);
    }
}

#[PublishLimit(daily: 10)]
public function store(): void {}
```

Композиционные атрибуты `Componenta\Policy\Attribute\AllOf` и `Componenta\Policy\Attribute\OneOf` принимают прямые политики и ссылки `Policy`:

```php
use Componenta\Policy\Attribute\OneOf;
use Componenta\Policy\Attribute\Policy;
use Componenta\Policy\Policies\OwnerPolicy;
use Componenta\Policy\Policies\RolePolicy;

#[OneOf(
    new RolePolicy('admin'),
    new OwnerPolicy(),
    new Policy(PremiumAccessPolicy::class),
)]
public function edit(int $id): void {}
```

## Точки расширения

| Что заменить | Контракт | Когда нужно |
|---|---|---|
| Источник политик | `PolicyProviderInterface` | Политики хранятся в БД, удаленном сервисе или другой системе конфигурации. |
| Создание контекста | `ContextFactoryInterface` | Нужно автоматически добавлять атрибуты в контекст или использовать свою реализацию `ContextInterface`. |
| Текущий актор для интеграций | `ActorProviderInterface` | Промежуточный слой или другая интеграция должны получать текущего пользователя без явной передачи в каждый вызов. |
| Политика | `PolicyInterface` | Нужно свое правило доступа. |
| Разрешение | `PermissionInterface` | У приложения свой enum или сущность разрешения. |
| Набор разрешений | `PermissionCollectionInterface` | Разрешения хранятся не в стандартной `PermissionCollection`. |

## Ошибки

| Ситуация | Исключение или результат |
|---|---|
| Политика вернула `DenyReason` | `check()` возвращает `AccessDenied`, `enforce()` выбрасывает `AccessDeniedException`. |
| Нет политики и включен `MissingPolicyBehavior::DENY` | Обычный отказ доступа. |
| Нет политики и включен `MissingPolicyBehavior::ALLOW` | Доступ разрешен. |
| Актор не реализует интерфейс, нужный политике | `InvalidPolicyActorException`. |
| В контексте нет обязательного атрибута | `MissingPolicyContextAttributeException`. |
| Атрибут контекста имеет неправильный тип | `InvalidPolicyContextAttributeException`. |
| Провайдер не нашел политику | Возвращает `null`, следующий провайдер может продолжить поиск. |

## Связанные пакеты

| Пакет | Что смотреть |
|---|---|
| [`componenta/policy-app`](../policy-app/README.ru.md) | Обнаружение атрибутов и компиляция карт политик для кеша. |
| [`componenta/cqrs`](../cqrs/README.ru.md) | Использование `PolicyMiddleware` для команд и запросов. |
| [`componenta/di`](../di-container/README.ru.md) | Создание политик через `FactoryInterface` и вызов сервисов из контейнера. |
| [`componenta/identity`](../identity/README.ru.md) | UUID-идентичность для акторов и владельцев ресурсов. |
