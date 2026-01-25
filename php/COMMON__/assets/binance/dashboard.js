$(function() {
	
	// hide dust checkbox
	$("#hide_dust").on("change", function() {
		$(".dust").toggleClass("visually-hidden");
	});
	
	
	// auto refresh
	let refreshTimeout
	function auto_refresh () {
		var auto_refresh = $("#auto_refresh").prop("checked");
		if (auto_refresh === true) {
			refreshTimeout = setTimeout(function () {
					location.reload();
				},
				5 * 60 * 1000 // 5 minutes
			);
		}
		else {
			if (typeof refreshTimeout !== 'undefined') {
				clearTimeout(refreshTimeout);
			}
		}
	}
	
	$("#auto_refresh").on("change", function() {
		auto_refresh();
	});
	
	auto_refresh();
	
	
	
});
