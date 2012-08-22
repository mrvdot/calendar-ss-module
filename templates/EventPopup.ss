<h4>$Title</h4>
<p class="time">$FormattedTime</p>
<p class="location"><span class="locationname">$Location</span><br />
	<% if Address %>
	$Address
	<% end_if %>
	<% if Phone %><br />$Phone<% end_if %></p>
<% if Description %>
	$Description
<% end_if %>