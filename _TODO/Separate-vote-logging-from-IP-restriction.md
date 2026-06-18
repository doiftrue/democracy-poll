# Разделить логирование и IP restriction 

See: https://wordpress.org/support/topic/log-data-ip-restriction/#post-9083794

Речь о том, что сейчас одна настройка делает сразу 2 вещи:

* логирует голосование в БД
* запрещает повторное голосование с одного IP / WP user

Проблема: люди хотят **видеть логи голосов**, включая IP, но **не хотят ограничивать голосование по IP**, потому что много пользователей могут сидеть за одним общим IP, например офис/университет.

TODO:

```text
Separate vote logging from IP restriction.

Currently "Log data & take visitor IP into consideration" does both:
- saves vote data to DB
- restricts repeat voting by IP / WP user

Need to split this into separate options:
1. Log vote data
   - Save vote logs to DB.
   - Store IP in logs even if IP restriction is disabled.

2. Restrict voting by IP / WP user
   - Prevent multiple votes from the same IP for guests.
   - Prevent multiple votes by the same WP user for logged-in users.
```

Дополнительно:

```text
Add option to set additional CSS class for poll wrapper.

Do not replace existing plugin classes because they are used by JS/CSS.
Only allow adding extra custom class.
```
