<h4>$Title</h4>
<p class="time">$FormattedTime</p>
<p class="location"><span class="locationname">$Location</span><br />
	$Address
	<% if Phone %><br />$Phone<% end_if %></p>
<% if Description %>
	$Description
<% end_if %>
<% if Aspect %>
	<div class="aspect">
		<% if AspectThumbnail %>
			<img class="aspectlogo" src="$AspectThumbnail.URL" />
		<% end_if %>
		<span class="aspecttitle">$Aspect.Title</span>
	</div>
<% end_if %>
