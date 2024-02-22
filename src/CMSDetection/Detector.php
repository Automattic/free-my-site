<?php

namespace DotOrg\FreeMySite\CMSDetection;

interface Detector {
	public function run( $url ) : Result;
}
