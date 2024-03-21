<?php

namespace DemocracyPoll;

function plugin(): Plugin {
	static $inst;
	$inst || $inst = new Plugin();

	return $inst;
}

function options(): Options {
	return plugin()->opt;
}


