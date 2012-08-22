<% if $Content %>
	<div class="content">
		<p>$Content</p>
	</div>
<% end_if %>
<h2 class="title">Events</h2>
<div id="events" class="inner">
	<% if $DisplayType == "List" %>
		<% if $UpcomingEvents %>
			<% loop $UpcomingEvents %>
			<span class="event"><h3>$Title</h3>
				<p><% if Location %>At $Location, <% end_if %>$FormattedTime<br />
					<% if Description %>
					{$Description.Summary}<br />
					<% end_if %>
					<% if Details %> | <a href="$Link" target="blank">See details</a><% end_if %>
				</p>
			</span>
			<% end_loop %>
		<% end_if %>
	<% else_if $DisplayType == "Calendar" %>
		<div id="eventcalendar"></div>
	<% end_if %>
</div>
