<?php

Class Event extends DataObject implements PermissionProvider {
	static $db = array(
		'Visibility' => 'Enum("Public,Private","Public")',
		'Title' => 'Varchar(255)',
		'Type' => 'Enum("Meeting,WorkBreak,DayEvent,Birthday","Meeting")',
		'Location' => 'Varchar(255)',
		'Address' => 'Text',
		'Phone' => 'Varchar(20)',
		'StartTime' => 'SS_DateTime',
		'EndTime' => 'SS_DateTime',
		'Description' => 'HTMLText',
		'Status' => 'Enum("Active,Past,Erased","Active")',
		'Source' => 'Enum("Database,Google","Database")',
		'GoogleLink' => 'Text',
		'GoogleID' => 'Varchar(255)',
		'URLSegment' => 'Varchar(255)',
	);

	static $default_sort = 'StartTime ASC, EndTime ASC';

	static $defaults = array(
		'Visiblity' => 'Public',
		'Status' => 'Active',
	);

	function providePermissions() {
		return array(
			'VIEW_PRIVATE_EVENTS' => 'Can view private events',
			'MANAGE_EVENTS' => 'Can add/edit/delete events'
		);
	}

	static function update_all($sc = null,$gcal = true) {
		if($gcal) {
			self::update_google_events($sc);
		}
		$events = DataObject::get('Event',"Status != 'Expired'");
		if(!$events) {
			return;
		}
		$es = $events->getIterator();
		foreach($es as $e) {
			$e->updateStatus();
		}
		return;
	}

	static function update_google_events($sc = null, $reset = false,$source = false) {
		$today = date('Y-m-d',time());
		if(!$sc) {
			$sc = SiteConfig::current_site_config();
		}
		$gUrl = $sc->GCalPrivateURL;
		$url = $gUrl . '?start-min='.$today.'&orderby=starttime&sortorder=a';
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($curl, CURLOPT_USERAGENT,
    "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		$result = curl_exec($curl);
		if(!$result) {
			return false;
		}
		$result = str_replace(array('gd:','openSearch:'),array('gd-','openSearch-'),$result);
		$calendar = new SimpleXMLElement($result);
		$entries = $calendar->entry;
		if($source) {
			return;
		}
		foreach($entries as $e) {
			$gid = (string) $e->id;
			$pos = strpos($gid,'full');
			$id = substr($gid,$pos + 5);
			$title = (string) $e->title;
			$link = (string) $e->link['href'];
			$start = (string) $e->{'gd-when'}['startTime'];
			$end = (string) $e->{'gd-when'}['endTime'];
			$location = (string) $e->{'gd-where'}['valueString'];
			$event = DataObject::get_one('Event',"GoogleID = '$id'");
			if(!$event) {
				$event = new Event();
			}
			$data = array(
				'Title' => $title,
				'GoogleLink' => $link,
				'GoogleID' => $id,
				'StartTime' => $start,
				'EndTime' => $end,
				'Location' => $location,
				'Source' => 'Google'
			);
			$event->update($data);
			$event->write();
			unset($data,$description,$link,$title,$start,$end,$location,$e,$event);
		}
		$date = new Datetime('now');
		$sc->GCalLastUpdated = $date->format('Y-m-d H:i:s');
		$sc->write();
		return;
	}

	static function get_upcoming_events($limit = null) {
		$query = "Status = 'Active'";
		$sc = SiteConfig::current_site_config();
		if(!$sc->UseGCal) {
			$query .= " AND Source != 'Google'";
		}
		$events = DataObject::get('Event',$query,'','',$limit);
		return $events;
	}

	static function get_dated_events($start = null, $end = null) {
		if(!$start) {
			$start = SS_Datetime::now()->Format('Y-m-d H:i:s');
		}
		if($end) {
			$query = "StartTime BETWEEN CAST('$start' AS datetime) AND CAST('$end' AS datetime)";
		} else {
			$query .= "StartTime >= CAST('$start' AS datetime)";
		}
		$events = DataObject::get('Event',$query);
		return $events ? $events : false;
	}

	static function get_next() {
		$events = self::getUpcomingEvents();
		if(!$events) {
			return false;
		}
		$e = $events->shift();
		while(!$e->canView()) {
			$e = $events->shift();
			if(!$e) {
				return false;
			}
		}
		return $e;
	}

	function canView($member = false) {
		$vis = $this->Visibility;
		if($vis == 'Public') {
			return true;
		} elseif (Permission::check('VIEW_PRIVATE_EVENTS')) {
			return true;
		} else {
			return false;
		}
	}

	function canEdit($member = false) {
		return Permission::check('MANAGE_EVENTS');
	}

	function canDelete($member = false) {
		return Permission::check('MANAGE_EVENTS');
	}

	function canCreate($member = false) {
		return Permission::check('MANAGE_EVENTS');
	}

	function getCMSFields() {
		$f = parent::getCMSFields();
		$f->removeByName('Source');
		$f->removeByName('GoogleLink');
		$f->removeByName('GoogleID');
		$f->removeByName('URLSegment');
		return $f;
	}//*/

	function updateStatus() {
		if($this->Status == 'Erased') {
			return;
		}
		$end = $this->EndTime ? $this->EndTime : $this->StartTime;
		$now = SS_DateTime::now()->Time();
		if(($this->Status == 'Active') && (strtotime($now) > strtotime($end))) {
			$this->Status = 'Past';
			$this->write();
			return;
		} elseif (($this->Status == 'Past') && (strtotime($now)	< strtotime($end))) {
			$this->Status = 'Active';
			$this->write();
			return;
		}
		return;
	}

	function getFormattedTime() {
		$start = $this->dbObject('StartTime');
		$end = $this->EndTime ? $this->dbObject('EndTime') : false;
		$time = $start->Format('l, F d, Y') . '&nbsp;&nbsp;/&nbsp;&nbsp;';
		if($start->Format('i') == '00') {
			$sform = 'g';
		} else {
			$sform = 'g:i';
		}
		if($end) {
			if($end->Format('i') == '00') {
				$eform = 'ga';
			} else {
				$eform = 'g:ia';
			}
			if($end->Format('a') !== $start->Format('a')) {
				$sform .= 'a';
			}
			$time .= $start->Format($sform) . ' to '.$end->Format($eform);
		} else {
			$sform .= 'a';
			$time .= $start->Format($sform);
		}
		return $time;
	}

	function Link() {
		$cp = DataObject::get_one('CalendarPage');
		return Controller::join_links($cp->Link(),'event',$this->URLSegment);
	}

	function onBeforeWrite() {
		if($this->StartTime) {
			$date = date('Y-m-d',strtotime($this->StartTime));
		} else {
			$date = '';
		}
		$segment = $this->Title .'-'.$date;
		$url = singleton('SiteTree')->generateURLSegment($segment);
		$this->URLSegment = $url;
		parent::onBeforeWrite();
	}
}
