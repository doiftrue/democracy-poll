<?php

namespace DemocracyPoll;

use DemocracyPoll\Infra\Container;

function container(): Container {
	return $GLOBALS['dem_test_container'] ??= new Container();
}
