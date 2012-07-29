<?php

class CalendarPage extends Page {
	static $db = array(
		'DisplayType' => 'Enum("List,Calendar","List")',
		);

	function getCMSFields() {
		$f = parent::getCMSFields();
		$f->addFieldToTab('Root.Content.CalendarOptions', new DropdownField('DisplayType','How do you want to display your events?', array('List' => 'List','Calendar'=>'Calendar')));
		$eventDOM = new DataObjectManager(
			$this,
			'Events',
			'Event',
			array(
				'Title' => 'Title',
				'Location' => 'Location',
				'StartTime' => 'Time',
				'Source' => 'Source'
			),
			''
			//"Status = 'Active'"
		);
		$f->addFieldToTab('Root.Content.Events',$eventDOM);
		return $f;
	}
}

class CalendarPage_Controller extends Page_Controller {
	function init() {
		$sc = SiteConfig::current_site_config();
		if($sc->UseGCal) {
			$int = $sc->GCalUpdateInterval;
			$last = strtotime($sc->GCalLastUpdated);
			$update = strtotime('+'.$int.' hours',$last);
			$gcal = (time() > $update) ? true : false;
		} else {
			$gcal = false;
		}
		Event::update_all($sc,$gcal);
		if($this->DisplayType == 'Calendar') {
			Requirements::css('Calendar/css/fullcalendar.css');
			Requirements::themedCSS('events');
			Requirements::javascript('http://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js');
			Requirements::javascript('Calendar/javascript/fullcalendar.min.js');
			Requirements::customScript(
				'(function($) {
					$content = $(\'#content\');
					$eventPopup = $(\'<div id="eventpopup" style="display:none;"><span class="close">X</span><span id="eventinfo"></span></div>\').prependTo($content);
					$eventPopup.children(\'span.close\').click(function() {
						$eventPopup.fadeOut();
					});
					cpos = $content.offset();
					$(\'#eventcalendar\').fullCalendar({
						dayNamesShort: [\'S\', \'M\', \'T\', \'W\', \'T\', \'F\', \'S\'],
						buttonText : {
							prev: \'&nbsp;&nbsp;\',
							next: \'|&nbsp;&nbsp;\'
						},
						viewDisplay: function() { Cufon.refresh(); $(\'th.fc-sat\').width(\'102px\'); },
						eventAfterRender: function() { Cufon.refresh(); },
						events: "'.$this->AjaxEventsLink.'",
						eventClick: function(event,js,view) {
							pgY = js.pageY - js.clientY;
							if(cpos.top < pgY) {
								popY = 75 + (pgY - cpos.top);
							} else {
								popY = 75;
							}
							$.get(event.url, function(data) {
								$eventPopup.css(\'marginTop\',popY + \'px\').children(\'#eventinfo\').html(data).end().fadeIn();
								Cufon.refresh();
							});
							return false;
						}
					});
					Cufon.replace("#eventpopup h4, #eventinfo .time, .fc-header-title, .fc-view th",{fontFamily: "DINSchriftMitAlt"});
					Cufon.replace(".fc-button-today span, .fc-event-title",{fontFamily: "DIN"});
					Cufon.replace("#eventinfo .aspecttitle", {fontFamily:"DinAlt"});
				})(jQuery);'
				);
		}
		Requirements::css('Calendar/css/CalendarPage.css');
		parent::init();
	}

	function getUpcomingEvents($limit = 10) {
		return Event::getUpcomingEvents($limit);
	}

	function getDatedEvents($start = null, $end = null) {
		return Event::getDatedEvents($start,$end);
	}

	function feed() {
		return Event::update_google_events('','',true);
	}

	function getAjaxEventsLink() {
		return Controller::join_links($this->Link(),'ajax/json/');
	}

	function ajax($request = null) {
		if(!$request) return false;
		//if(!$this->ajax) return false;
		$format = $request->param('ID') ? $request->param('ID') : 'json';
		$start = isset($_REQUEST['start']) ? $_REQUEST['start'] : time();
		$end = isset($_REQUEST['end']) ? $_REQUEST['end'] : (time() + (7 * 24 * 60 * 60));
		$startF = date('Y-m-d H:i:s',$start);
		$endF = date('Y-m-d H:i:s',$end);
		$events = $this->getDatedEvents($startF,$endF);
		if(!$events) return false;
		$es = $events->getIterator();
		$evArray = array();
		if($format == 'json') { //code for xml later
			$count = 1;
			foreach($es as $e) {
				if($end = $e->EndTime) {
					$endK = 'end';
					$endV = $end;
				} else {
					$endK = 'allDay';
					$endV = true;
				}
				$evArray[] = array(
					'title' => $e->Title,
					'start' => $e->StartTime,
					'url' => $e->Link(),
					'className' => $e->Status,
					$endK => $endV
					);
				$count++;
			}
			$json = json_encode($evArray);
			return $json;
		}
	}

	function event($request = null) {
		if(!$request) return false;
		//if($this->ajax) code non-popup version
		$id = $request->param('ID');
		if(!$id) return false;
		$event = DataObject::get_one('Event',"URLSegment = '$id'");
		if(!$event) return false;
		return $this->renderWith('EventPopup',$event);
	}
}
