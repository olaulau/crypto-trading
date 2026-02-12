$(function() {
	
	// hide dust checkbox
	$("#hide_dust").on("change", function() {
		$(".dust").toggleClass("d-none");
	});
	
	
	// auto refresh
	let refreshTimeout
	function auto_refresh () {
		var auto_refresh = $("#auto_refresh").prop("checked");
		if (auto_refresh === true) {
			refreshTimeout = setTimeout(function () {
					location.reload();
				},
				5 * 60 * 1000 // 5 minuts in millisecondes
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
	
	
	// show balance
	function show_balance () {
		var show_balance = $("#show_balance").prop("checked");
		if (show_balance) {
			$(".balance_cell").removeClass("d-none");
		}
		else {
			$(".balance_cell").addClass("d-none");
		}
	}
	$("#show_balance").on("change", function() {
		show_balance () ;
	});
	show_balance () ;
	
});
