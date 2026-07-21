-- Demo data for manual E2E tests and screenshots.
--
-- Run this after activating Democracy Poll. The script only ADDS data and does
-- not delete or alter existing polls. It assumes the usual WordPress `wp_`
-- prefix; replace `wp_` below if the site uses another database prefix.
--
-- It creates ten polls, 45 answers (including new user answers) and 30 vote
-- logs. Answer vote totals and `users_voted` are calculated from those logs.

START TRANSACTION;

SET @dem_now := UNIX_TIMESTAMP();

INSERT INTO wp_democracy_q
	(question, added, added_user, end, users_voted, democratic, active, open, multiple, forusers, revote, show_results, answers_order, in_posts, note)
VALUES
	('Which content format do you find most useful?', @dem_now - 2592000, 1, 0, 0, 1, 1, 1, 0, 0, 1, 1, 'by_id', '', 'Includes a user-submitted answer and allows revoting.'),
	('How often do you read our blog?', @dem_now - 2419200, 1, 0, 0, 0, 1, 1, 0, 0, 0, 1, 'alphabet', '', 'A regular single-choice poll without revoting.'),
	('Which topics should we include in the next newsletter?', @dem_now - 2246400, 2, 0, 0, 1, 1, 1, 2, 0, 1, 1, 'by_winner', '', 'Visitors can select up to two answers.'),
	('Which tool do you use every day?', @dem_now - 2073600, 2, 0, 0, 0, 1, 1, 0, 1, 0, 1, '', '', 'This poll is available to registered users only.'),
	('Which was the best release this spring?', @dem_now - 5184000, 1, @dem_now - 86400, 0, 0, 1, 0, 0, 0, 0, 1, 'by_winner', '', 'A closed poll with existing results.'),
	('Will you attend our next meetup?', @dem_now - 172800, 1, @dem_now + 1209600, 0, 0, 1, 1, 0, 0, 1, 1, '', '', 'This poll has a future closing date.'),
	('What matters most when choosing hosting?', @dem_now - 1468800, 3, 0, 0, 1, 1, 1, 3, 0, 1, 0, 'mix', '', 'A multiple-choice poll with a limit of three answers.'),
	('What coffee do you prefer in the morning?', @dem_now - 950400, 2, 0, 0, 0, 0, 1, 0, 0, 1, 1, 'by_id', '', 'Inactive: it must not appear in a random poll.'),
	('How useful are code examples to you?', @dem_now - 864000, 1, 0, 0, 1, 1, 1, 0, 0, 0, 0, 'by_winner', '', 'The results link is hidden before voting.'),
	('What should we improve on the site first?', @dem_now - 604800, 3, 0, 0, 1, 1, 1, 2, 0, 1, 1, 'alphabet', '', 'A long answer list for testing the poll height.');

SET @p1 := LAST_INSERT_ID();
SET @p2 := @p1 + 1;
SET @p3 := @p1 + 2;
SET @p4 := @p1 + 3;
SET @p5 := @p1 + 4;
SET @p6 := @p1 + 5;
SET @p7 := @p1 + 6;
SET @p8 := @p1 + 7;
SET @p9 := @p1 + 8;
SET @p10 := @p1 + 9;

INSERT INTO wp_democracy_a (qid, answer, votes, aorder, added_by) VALUES
	(@p1, 'In-depth articles', 0, 1, ''), (@p1, 'Short posts', 0, 2, ''), (@p1, 'Videos', 0, 3, ''), (@p1, 'Podcasts', 0, 4, ''), (@p1, 'Real-world project breakdowns', 0, 5, '203.0.113.11-new'),
	(@p2, 'Every day', 0, 0, ''), (@p2, 'A few times a week', 0, 0, ''), (@p2, 'A couple of times a month', 0, 0, ''),
	(@p3, 'WordPress', 0, 1, ''), (@p3, 'PHP', 0, 2, ''), (@p3, 'JavaScript', 0, 3, ''), (@p3, 'Performance', 0, 4, ''), (@p3, 'Security', 0, 5, ''), (@p3, 'Working with clients', 0, 6, ''),
	(@p4, 'Code editor', 0, 0, ''), (@p4, 'Terminal', 0, 0, ''), (@p4, 'Task manager', 0, 0, ''), (@p4, 'Documentation', 0, 0, ''),
	(@p5, 'New editor', 0, 0, ''), (@p5, 'Improved performance', 0, 0, ''), (@p5, 'New blocks', 0, 0, ''), (@p5, 'Updated design', 0, 0, ''), (@p5, 'PHP 8.3 support', 0, 0, ''),
	(@p6, 'Yes, definitely', 0, 0, ''), (@p6, 'Maybe, if there is an online stream', 0, 0, ''), (@p6, 'Not planning to attend', 0, 0, ''),
	(@p7, 'Speed', 0, 0, ''), (@p7, 'Support', 0, 0, ''), (@p7, 'Price', 0, 0, ''), (@p7, 'Backups', 0, 0, ''),
	(@p8, 'Espresso', 0, 1, ''), (@p8, 'Cappuccino', 0, 2, ''), (@p8, 'Filter coffee', 0, 3, ''), (@p8, 'Tea', 0, 4, ''), (@p8, 'Water', 0, 5, ''),
	(@p9, 'Very useful', 0, 0, ''), (@p9, 'I sometimes use them', 0, 0, ''), (@p9, 'I prefer written explanations', 0, 0, ''), (@p9, 'I would like more examples', 0, 0, '198.51.100.42-new'),
	(@p10, 'Search', 0, 0, ''), (@p10, 'Mobile version', 0, 0, ''), (@p10, 'Page load speed', 0, 0, ''), (@p10, 'Navigation', 0, 0, ''), (@p10, 'Dark mode', 0, 0, ''), (@p10, 'Accessibility', 0, 0, ''), (@p10, 'Account dashboard', 0, 0, '5-new');

SET @a1 := LAST_INSERT_ID();
-- Answer IDs follow the order of the preceding INSERT (45 rows).
SET @a2 := @a1 + 1; SET @a3 := @a1 + 2; SET @a4 := @a1 + 3; SET @a5 := @a1 + 4;
SET @a6 := @a1 + 5; SET @a7 := @a1 + 6; SET @a8 := @a1 + 7;
SET @a9 := @a1 + 8; SET @a10 := @a1 + 9; SET @a11 := @a1 + 10; SET @a12 := @a1 + 11; SET @a13 := @a1 + 12; SET @a14 := @a1 + 13;
SET @a15 := @a1 + 14; SET @a16 := @a1 + 15; SET @a17 := @a1 + 16; SET @a18 := @a1 + 17;
SET @a19 := @a1 + 18; SET @a20 := @a1 + 19; SET @a21 := @a1 + 20; SET @a22 := @a1 + 21; SET @a23 := @a1 + 22;
SET @a24 := @a1 + 23; SET @a25 := @a1 + 24; SET @a26 := @a1 + 25;
SET @a27 := @a1 + 26; SET @a28 := @a1 + 27; SET @a29 := @a1 + 28; SET @a30 := @a1 + 29;
SET @a31 := @a1 + 30; SET @a32 := @a1 + 31; SET @a33 := @a1 + 32; SET @a34 := @a1 + 33; SET @a35 := @a1 + 34;
SET @a36 := @a1 + 35; SET @a37 := @a1 + 36; SET @a38 := @a1 + 37; SET @a39 := @a1 + 38;
SET @a40 := @a1 + 39; SET @a41 := @a1 + 40; SET @a42 := @a1 + 41; SET @a43 := @a1 + 42; SET @a44 := @a1 + 43; SET @a45 := @a1 + 44;

INSERT INTO wp_democracy_log (ip, qid, aids, userid, date, expire, ip_info, fingerprint) VALUES
	('203.0.113.10', @p1, @a1, 0, NOW() - INTERVAL 26 DAY, @dem_now + 2592000, 'US|United States|New York', SHA2('p1-anon-1',256)),
	('198.51.100.20', @p1, @a3, 2, NOW() - INTERVAL 20 DAY, @dem_now + 2592000, 'DE|Germany|Berlin', ''),
	('203.0.113.11', @p1, @a5, 0, NOW() - INTERVAL 15 DAY, @dem_now + 2592000, 'GB|United Kingdom|London', SHA2('p1-anon-2',256)),
	('192.0.2.31', @p1, @a1, 3, NOW() - INTERVAL 8 DAY, @dem_now + 2592000, 'CA|Canada|Toronto', ''),
	('198.51.100.21', @p2, @a6, 4, NOW() - INTERVAL 25 DAY, @dem_now + 2592000, 'FR|France|Paris', ''),
	('203.0.113.12', @p2, @a7, 0, NOW() - INTERVAL 19 DAY, @dem_now + 2592000, 'ES|Spain|Madrid', SHA2('p2-anon-1',256)),
	('192.0.2.32', @p2, @a7, 0, NOW() - INTERVAL 3 DAY, @dem_now + 2592000, 'PL|Poland|Warsaw', SHA2('p2-anon-2',256)),
	('198.51.100.30', @p3, CONCAT(@a9, ',', @a11), 1, NOW() - INTERVAL 21 DAY, @dem_now + 2592000, 'US|United States|Austin', ''),
	('203.0.113.13', @p3, CONCAT(@a10, ',', @a12), 0, NOW() - INTERVAL 14 DAY, @dem_now + 2592000, 'NL|Netherlands|Amsterdam', SHA2('p3-anon-1',256)),
	('192.0.2.33', @p3, CONCAT(@a9, ',', @a13), 5, NOW() - INTERVAL 6 DAY, @dem_now + 2592000, 'UA|Ukraine|Kyiv', ''),
	('198.51.100.40', @p4, @a15, 2, NOW() - INTERVAL 18 DAY, @dem_now + 2592000, 'DE|Germany|Munich', ''),
	('203.0.113.14', @p4, @a16, 3, NOW() - INTERVAL 9 DAY, @dem_now + 2592000, 'CZ|Czechia|Prague', ''),
	('192.0.2.41', @p4, @a15, 4, NOW() - INTERVAL 2 DAY, @dem_now + 2592000, 'FI|Finland|Helsinki', ''),
	('198.51.100.50', @p5, @a20, 1, NOW() - INTERVAL 55 DAY, 0, 'US|United States|Boston', ''),
	('203.0.113.15', @p5, @a19, 0, NOW() - INTERVAL 48 DAY, 0, 'GB|United Kingdom|Manchester', SHA2('p5-anon-1',256)),
	('192.0.2.51', @p5, @a20, 5, NOW() - INTERVAL 40 DAY, 0, 'IT|Italy|Milan', ''),
	('198.51.100.60', @p6, @a24, 0, NOW() - INTERVAL 2 DAY, @dem_now + 2592000, 'US|United States|Seattle', SHA2('p6-anon-1',256)),
	('203.0.113.16', @p6, @a25, 2, NOW() - INTERVAL 1 DAY, @dem_now + 2592000, 'JP|Japan|Tokyo', ''),
	('192.0.2.61', @p7, CONCAT(@a27, ',', @a28, ',', @a30), 0, NOW() - INTERVAL 16 DAY, @dem_now + 2592000, 'SE|Sweden|Stockholm', SHA2('p7-anon-1',256)),
	('198.51.100.70', @p7, CONCAT(@a27, ',', @a29), 3, NOW() - INTERVAL 10 DAY, @dem_now + 2592000, 'NO|Norway|Oslo', ''),
	('203.0.113.17', @p7, @a28, 0, NOW() - INTERVAL 5 DAY, @dem_now + 2592000, 'AU|Australia|Sydney', SHA2('p7-anon-2',256)),
	('192.0.2.71', @p8, @a32, 0, NOW() - INTERVAL 11 DAY, @dem_now + 2592000, 'BR|Brazil|Sao Paulo', SHA2('p8-anon-1',256)),
	('198.51.100.80', @p8, @a31, 1, NOW() - INTERVAL 7 DAY, @dem_now + 2592000, 'PT|Portugal|Lisbon', ''),
	('203.0.113.18', @p8, @a32, 4, NOW() - INTERVAL 4 DAY, @dem_now + 2592000, 'IE|Ireland|Dublin', ''),
	('192.0.2.81', @p9, @a36, 0, NOW() - INTERVAL 13 DAY, @dem_now + 2592000, 'US|United States|Chicago', SHA2('p9-anon-1',256)),
	('198.51.100.42', @p9, @a39, 0, NOW() - INTERVAL 1 DAY, @dem_now + 2592000, 'CH|Switzerland|Zurich', SHA2('p9-anon-2',256)),
	('203.0.113.19', @p10, CONCAT(@a40, ',', @a42), 2, NOW() - INTERVAL 6 DAY, @dem_now + 2592000, 'DE|Germany|Hamburg', ''),
	('192.0.2.91', @p10, CONCAT(@a41, ',', @a44), 0, NOW() - INTERVAL 5 DAY, @dem_now + 2592000, 'AT|Austria|Vienna', SHA2('p10-anon-1',256)),
	('198.51.100.90', @p10, @a45, 5, NOW() - INTERVAL 3 DAY, @dem_now + 2592000, 'EE|Estonia|Tallinn', ''),
	('203.0.113.20', @p10, CONCAT(@a42, ',', @a43), 0, NOW() - INTERVAL 1 DAY, @dem_now + 2592000, 'DK|Denmark|Copenhagen', SHA2('p10-anon-2',256));

UPDATE wp_democracy_a AS answer
SET votes = (
	SELECT COUNT(*)
	FROM wp_democracy_log AS log
	WHERE log.qid = answer.qid AND FIND_IN_SET(answer.aid, log.aids)
)
WHERE answer.qid BETWEEN @p1 AND @p10;

UPDATE wp_democracy_q AS poll
SET users_voted = (
	SELECT COUNT(*) FROM wp_democracy_log AS log WHERE log.qid = poll.id
)
WHERE poll.id BETWEEN @p1 AND @p10;

COMMIT;

-- The generated poll IDs are returned by this final result set.
SELECT id, question, users_voted, open, active, multiple, forusers
FROM wp_democracy_q
WHERE id BETWEEN @p1 AND @p10
ORDER BY id;
