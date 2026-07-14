<?php

namespace DemocracyPoll;

use DemocracyPoll\Libs\Container;

function container(): Container {
	return $GLOBALS['dem_test_container'] ??= new Container();
}
