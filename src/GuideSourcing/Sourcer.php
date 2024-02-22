<?php

namespace DotOrg\FreeMySite\GuideSourcing;

interface Sourcer {
	public function fetch() : string;
}
