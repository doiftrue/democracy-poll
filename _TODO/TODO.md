# Общий список


Рефакторинг:
- Убрать зависимость на Plugin_Initor в Admin_Page_Design



* ADD: Для каждого опроса своя высота разворачивания. Хотел сегодня прикрутить голосование помимо сайдбара ещё и в саму статью (там высота нужна была больше), не получилось. Она к сожалению фиксирована для всех опросов.
* ADD: Возомжность добавлять свои темы (ссылку на css файл с темой)?
* ADD: возможность подключать стили как файл!
* ADD: возможность показывать пользователю текст после того, как он проголосует (типа "ваш голос очено важен для нас" и т.п.)
* ADD: лимит голосования, чтобы участники обязательно должны были выбрать, например, 3 пункта, чтобы проголосовать.
* ADD: sorting on archive page
* ADD: Сделать опрос активным в указанную дату?
* ADD: cron: shadule polls opening & activation
* ADD: quick edit (ajaxs handler for buttons in polls list) - https://wordpress.org/support/topic/suggestion-quick-edit/
* ADD: show link to post at the bottom of poll, if it attached to one post (has one in_posts ID)
* ADD: Group polls
* 
* ADD: Речь идёт о премодерации, чтобы пользователь предложил свой вариант, а публичным данный вариант станет после одобрения администратором.
* ADD: The ability to have a list of all active polls on one front end page would be nice.
* ADD: Добавить возможность "прикреплять" опрос к конкретному посту/странице вставкой шорткода не в тексте, а сделать метабокс (причем с нормальным выбором опроса из списка). Это позволит добавлять опрос в любое место на странице (согласно дизайну) и только для тех постов/страниц, где подключен опрос.

* Is there any possibility in a future update to include an option to display only the percentage(% of votes instead of the number of votes? (and allow the total number of votes to be hidden)  https://wordpress.org/support/topic/of-votes-instead-of-number-of-votes/

* Param to owerride Question text in shortcode, e.g. [yop_poll id="123" question="New question text"] See request: https://wordpress.org/support/topic/shortcode-option-12/

* how i can hide the number of votes from te results of the poll but keep the percentage. Is there something i can change in the css? https://wordpress.org/support/topic/voting-results-hide-number-of-votes-but-show/ 
  * Need to change `_x( '%s - %s%% of all votes', 'front', 'democracy-poll' ),` refactor to use placeholders - {votes} {percent} it will give ability to change the text and remove for example the votes.


* Очищать кэш страницы при изменении опроса (у тех страниц к которым опрос прикреплен). Добавить хук что опрос изменился (любой и конкретный), чтобы можно было на этом хуке очистить какой-либо кэш (например, кэш плагина WP Super Cache). https://wordpress.org/support/topic/managing-page-caching-in-a-different-plugin-4/

* Добавить возможность менять текст и добавлять ссылку в текст "Only registered users can vote". I want non-registered users on my site to be directed to a specific (https://accradailypost.com/register) when they click on >> Only registered users can vote. Login to vote.<< not the default WordPress login page. How do I do this? Please help me. https://wordpress.org/support/topic/default-login-link-for-non-voters/

* P5: Быстрая привязка и создание опроса для поста: https://wordpress.org/support/topic/auto-generation-of-unique-poll-per-post/ подумать, типа у каждого поста должен быть опрос, как его создавать быстро...

* Add option to set additional CSS class for poll wrapper. Do not replace existing plugin classes because they are used by JS/CSS. Only allow adding extra custom class.
