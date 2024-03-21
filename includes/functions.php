<?php

function democr(): \DemocracyPoll\Plugin {
	static $inst;
	$inst || $inst = new \DemocracyPoll\Plugin();

	return $inst;
}

function demopt(): \DemocracyPoll\Options {
	return democr()->opt;
}


