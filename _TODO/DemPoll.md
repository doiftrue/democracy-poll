# DemPoll: публичный контракт который был до версии 6.1.1

Короткий снимок текущей публичной поверхности `DemPoll` перед декомпозицией и
будущим переименованием в `Poll`.

Источники контракта: `classes/DemPoll.php`, `classes/Poll_Renderer.php`,
`classes/Poll_Service.php`, `classes/Poll_Answer.php`,
`includes/theme-functions.php`.

## Визуальная схема объекта

```text
DemPoll
|
+-- public state
|   +-- id: int
|   +-- question: string
|   +-- added: int
|   +-- end: int
|   +-- added_user: int
|   +-- users_voted: int
|   +-- democratic: bool
|   +-- active: bool
|   +-- open: bool
|   +-- multiple: int
|   +-- forusers: bool
|   +-- revote: bool
|   +-- show_results: bool
|   +-- answers_order: string
|   +-- in_posts: string
|   +-- note: string
|   +-- blocked_by_not_logged: bool
|
+-- dbdata: object|null
|   +-- raw row from $wpdb->democracy_q
|
+-- answers: Poll_Answer[]              [read]
|   +-- Poll_Answer
|       +-- aid: string
|       +-- qid: string
|       +-- answer: string
|       +-- votes: int
|       +-- aorder: int
|       +-- added_by: string
|
+-- renderer: Poll_Renderer
|   +-- not_show_results: bool
|   +-- get_screen()
|   +-- get_vote_screen()
|   +-- get_result_screen()
|   +-- voted_notice_html()
|
+-- service: Poll_Service
|   +-- cookie_key: string
|   +-- vote()
|   +-- delete_vote()
|   +-- get_voted_for()
|   +-- get_user_vote_logs()
|   +-- get_cookie_expire_time()
|   +-- set_cookie()
|   +-- unset_cookie()
|
+-- vote state
    +-- voting_blocked: bool
    +-- blockVoting: bool               [legacy alias]
    +-- voted_for: string
    +-- votedFor: string                [legacy alias]
    +-- has_voted: bool
    +-- blockForVisitor: bool           [legacy alias]
```

## Pseudo-interfaces object shape

Это не реальные PHP-интерфейсы, а схемы публичного object shape: в них фиксируются
свойства и методы, которые доступны извне. Текущий способ реализации свойств здесь
не важен.

```php
DemPoll {
    public DemocracyPoll\Poll_Renderer $renderer;
    public DemocracyPoll\Poll_Service $service;
    public bool $blocked_by_not_logged = false;
    public ?object $dbdata = null;

    public int $id = 0;
    public string $question = "";
    public int $added = 0;
    public int $end = 0;
    public int $added_user = 0;
    public int $users_voted = 0;
    public bool $democratic = false;
    public bool $active = false;
    public bool $open = false;
    public int $multiple = 0;
    public bool $forusers = false;
    public bool $revote = false;
    public bool $show_results = false;
    public string $answers_order = "";
    public string $in_posts = "";
    public string $note = "";

    // accessible state properties
    public DemocracyPoll\Poll_Answer[] $answers; // read
    public bool $voting_blocked;                 // read/write
    public bool $blockVoting;                    // read/write, legacy alias
    public string $voted_for;                    // read/write
    public string $votedFor;                     // read/write, legacy alias
    public bool $has_voted;                      // read/write
    public bool $blockForVisitor;                // read, legacy alias

    public function __construct(object|int $poll_id);
    public static function get_db_data(int|string $poll_id): ?object;
    public function re_set_answers(): void;
}
```

```php
DemocracyPoll\Poll_Renderer {
    public bool $not_show_results = false;

    public function __construct(DemPoll $poll);
    public function get_screen(
        string $show_screen = "vote",
        string $before_title = "",
        string $after_title = ""
    ): string|false;
    public function get_vote_screen(): string;
    public function get_result_screen(): string;
    public static function voted_notice_html($msg = ""): string;
}
```

```php
DemocracyPoll\Poll_Service {
    public string $cookie_key;

    public function __construct(DemPoll $poll);
    public function vote(string|array $aids): WP_Error|string;
    public function delete_vote(): void;
    public function get_voted_for(): string;
    public function get_user_vote_logs(): array;
    public function get_cookie_expire_time(): int;
    public function set_cookie(string $value = "", int $expire = 0): void;
    public function unset_cookie(): void;
}
```

```php
DemocracyPoll\Poll_Answer {
    public string $aid;
    public string $qid;
    public string $answer;
    public int $votes;
    public int $aorder;
    public string $added_by;

    public function __construct(object|array $data);
}
```

```php
// $poll->dbdata shape: raw DB row from $wpdb->democracy_q.
object {
    public string|int $id;
    public string $question;
    public string|int $added;
    public string|int $end;
    public string|int $added_user;
    public string|int $users_voted;
    public string|int $democratic;
    public string|int $active;
    public string|int $open;
    public string|int $multiple;
    public string|int $forusers;
    public string|int $revote;
    public string|int $show_results;
    public string $answers_order;
    public string $in_posts;
    public string $note;
}
```

## Создание объекта

- `new DemPoll( object|int $poll_id )`
  - `int`/numeric: загружает строку опроса через `DemPoll::get_db_data()`.
  - `object`: использует переданный DB-row как `$poll->dbdata`.
  - falsy или несуществующий опрос: объект остается с `$poll->id === 0`; `$renderer`
    и `$service` при этом не инициализируются.
- `democracy_get_poll( $poll_id ): DemPoll` - глобальная фабрика-обертка.
- `DemPoll::get_db_data( int|string $poll_id ): ?object`
  - поддерживает ID, `'rand'`, `'last'`;
  - возвращает raw DB-row или `null`;
  - применяет фильтр `dem_get_poll`.

## Публичные свойства DemPoll

### Инфраструктурные

| Свойство | Тип | Значение |
| --- | --- | --- |
| `$renderer` | `DemocracyPoll\Poll_Renderer` | Рендеринг HTML. Есть только у валидного опроса. |
| `$service` | `DemocracyPoll\Poll_Service` | Голосование, cookie, logs. Есть только у валидного опроса. |
| `$blocked_by_not_logged` | `bool` | `true`, если голосование закрыто для неавторизованного посетителя. |
| `$dbdata` | `?object` | Исходная строка опроса из БД. |

### Поля опроса

| Свойство | Тип | Значение |
| --- | --- | --- |
| `$id` | `int` | ID опроса. `0` означает, что опрос не найден/не загружен. |
| `$question` | `string` | Заголовок/вопрос. |
| `$added` | `int` | UNIX timestamp создания. |
| `$end` | `int` | UNIX timestamp окончания, `0` если не задан. |
| `$added_user` | `int` | ID автора. |
| `$users_voted` | `int` | Количество проголосовавших пользователей. |
| `$democratic` | `bool` | Можно добавлять свой ответ. Может быть принудительно `false` опцией `democracy_off`. |
| `$active` | `bool` | Опрос активен. |
| `$open` | `bool` | Голосование открыто. Может стать `false` при создании объекта, если `$end` истек. |
| `$multiple` | `int` | Максимум выбираемых ответов. `0`/`1` работают как одиночный выбор. |
| `$forusers` | `bool` | Только для зарегистрированных пользователей. |
| `$revote` | `bool` | Можно переголосовать. Может быть принудительно `false` опцией `revote_off`. |
| `$show_results` | `bool` | Показывать результаты после голосования. |
| `$answers_order` | `string` | Порядок ответов: `by_winner`, `by_id`, `alphabet`, `mix` или пусто для опции по умолчанию. |
| `$in_posts` | `string` | CSV со связанными post IDs. |
| `$note` | `string` | Дополнительная заметка к опросу. |

## Доступные свойства состояния

Эти свойства входят в контракт как доступные свойства объекта. То, что сейчас часть
из них вычисляется лениво или проксируется через технические методы, не является
требованием к будущему `Poll`.

| Свойство | Тип | Доступ | Значение |
| --- | --- | --- | --- |
| `$answers` | `DemocracyPoll\Poll_Answer[]` | read | Ответы из БД, отсортированные и пропущенные через `dem_set_answers`. |
| `$voting_blocked` | `bool` | read/write | Голосование заблокировано: не залогинен, опрос закрыт или пользователь уже голосовал. |
| `$blockVoting` | `bool` | read/write | Legacy alias для `$voting_blocked`. |
| `$voted_for` | `string` | read/write | CSV answer IDs, за которые голосовал текущий пользователь. |
| `$votedFor` | `string` | read/write | Legacy alias для `$voted_for`. |
| `$has_voted` | `bool` | read/write | Есть ли голос текущего пользователя. |
| `$blockForVisitor` | `bool` | read | Legacy alias для `$blocked_by_not_logged`. |

Требования к совместимости:

- свойства должны быть доступны как `$poll->property`;
- legacy aliases должны возвращать и принимать те же значения, что основные свойства;
- `$answers` и `$blockForVisitor` достаточно сохранить как read-only снаружи;
- способ хранения, вычисления, кеширования или lazy-load не фиксируется.

## Публичные методы DemPoll

Технические `__get()`, `__set()`, `__isset()` текущего `DemPoll` не являются
самостоятельным целевым контрактом: важна доступность свойств выше.

| Метод | Возврат | Контракт |
| --- | --- | --- |
| `__construct( object|int $poll_id )` | `void` | Загружает DB-row, приводит поля к типам, создает `$renderer`/`$service`, закрывает истекший опрос. |
| `DemPoll::get_db_data( $poll_id )` | `?object` | Raw poll DB-row по ID, `'rand'` или `'last'`. |
| `re_set_answers()` | `void` | Принудительно перезагружает `$answers`. Используется после vote/delete. |

## Контракт `$poll->renderer`

Тип: `DemocracyPoll\Poll_Renderer`.

| Член | Тип/возврат | Контракт |
| --- | --- | --- |
| `$not_show_results` | `bool` | Флаг: результаты сейчас скрыты, надо показывать экран голосования. |
| `get_screen( string $show_screen = 'vote', string $before_title = '', string $after_title = '' )` | `string|false` | Полный HTML опроса. `$show_screen`: `vote`, `voted`, `force_vote`. |
| `get_vote_screen()` | `string` | HTML формы голосования без внешней обертки `.democracy`. |
| `get_result_screen()` | `string` | HTML экрана результатов без внешней обертки `.democracy`. |
| `Poll_Renderer::voted_notice_html( $msg = '' )` | `string` | HTML notice о невозможности/повторности голосования. |

## Контракт `$poll->service`

Тип: `DemocracyPoll\Poll_Service`.

| Член | Тип/возврат | Контракт |
| --- | --- | --- |
| `$cookie_key` | `string` | Имя cookie: `demPoll_{poll_id}`. |
| `vote( string|array $aids )` | `WP_Error|string` | Добавляет голос. `$aids` - массив или `~`-разделенная строка IDs/свободного ответа. Возвращает CSV IDs или ошибку. |
| `delete_vote()` | `void` | Удаляет голос текущего пользователя, если `$poll->revote` разрешен. |
| `get_voted_for()` | `string` | Возвращает CSV IDs из logs или cookie. |
| `get_user_vote_logs()` | `object[]` | Возвращает актуальные строки `democracy_log` для текущего пользователя/IP. |
| `get_cookie_expire_time()` | `int` | UTC timestamp истечения cookie/log. |
| `set_cookie( string $value = '', int $expire = 0 )` | `void` | Ставит cookie и обновляет `$_COOKIE`; по умолчанию берет `$poll->voted_for`. |
| `unset_cookie()` | `void` | Удаляет cookie и очищает `$_COOKIE[$cookie_key]`. |

Побочные эффекты `vote()` и `delete_vote()`:

- меняют таблицы answers/questions/logs;
- меняют cookie и `$_COOKIE`;
- обновляют `$poll->users_voted`, `$poll->dbdata->users_voted`,
  `$poll->voting_blocked`, `$poll->has_voted`, `$poll->voted_for`;
- перезагружают `$poll->answers`.

## Контракт элементов `$poll->answers`

Тип элемента: `DemocracyPoll\Poll_Answer`.

| Свойство | Тип | Значение |
| --- | --- | --- |
| `$aid` | `string` | ID ответа. |
| `$qid` | `string` | ID опроса. |
| `$answer` | `string` | Текст ответа. |
| `$votes` | `int` | Количество голосов. |
| `$aorder` | `int` | Пользовательский порядок. |
| `$added_by` | `string` | Кто добавил ответ; непусто для visitor/free answer. |

## Hooks, связанные с контрактом

| Hook | Тип | Аргументы |
| --- | --- | --- |
| `dem_get_poll` | filter | `?object $poll_data` |
| `dem_set_answers` | filter | `Poll_Answer[] $answers, DemPoll $poll` |
| `dem_vote_screen_answer` | filter | `Poll_Answer $answer` |
| `dem_vote_screen` | filter | `string $html, DemPoll $poll` |
| `dem_result_screen_answer` | filter | `Poll_Answer $answer` |
| `dem_result_screen` | filter | `string $html, DemPoll $poll` |
| `dem_voted` | action | `string $voted_for, DemPoll $poll` |
| `dem_vote_deleted` | action | `DemPoll $poll` |
| `dem_poll_screen_choose` | filter | `string $screen, DemPoll $poll` |

## Минимум для legacy-совместимости будущего Poll

- оставить `DemPoll` как alias/adapter или совместимую оболочку над `Poll`;
- сохранить public DB-поля и их типы;
- сохранить доступные свойства состояния и legacy aliases: `$votedFor`, `$blockVoting`,
  `$blockForVisitor`;
- сохранить `$poll->renderer` и `$poll->service` или совместимые прокси;
- сохранить `DemPoll::get_db_data()` и `democracy_get_poll()`;
- сохранить shape `$poll->answers` как массив объектов с полями `Poll_Answer`;
- не менять сигнатуры hooks, где наружу передается `DemPoll`.
