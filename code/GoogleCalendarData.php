<?php

class GoogleCalendarData extends Extension {
	function extraStatics() {
		return array(
			'db' => array(
				'UseGCal' => 'Boolean',
				'GCalLastUpdated' => 'SS_DateTime',
				'GCalUpdateInterval' => 'Int',
				'GCalPrivateURL' => 'Varchar(255)'
			)
		);
	}

	function updateCMSFields(&$f) {
		$tab = 'Root.GoogleCalendarOptions';
		$intDD = array(
			1 => 'Hourly',
			3 => 'Every 3 hours',
			12 => 'Every 12 hours',
			24 => 'Daily'
		);
		$f->addFieldToTab($tab, new CheckboxField('UseGCal','Do you want to use Google calendar for your events?'));
		$f->addFieldToTab($tab, new TextField('GCalPrivateURL','Private url to xml feed for calendar'));
		$f->addFieldToTab($tab,new DropdownField('GCalUpdateInterval','Update interval for Google Calendar events',$intDD));
	}
}
