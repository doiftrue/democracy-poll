<?php

namespace DemocracyPoll;

use DemocracyPoll\Infra\Container;

function container(): Container {
	return $GLOBALS['democracy_poll_test_container'] ??= new Container();
}
