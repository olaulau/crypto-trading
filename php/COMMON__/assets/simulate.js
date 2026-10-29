$(function() {
	
	// ask for confirmation if we are going to run many simulations
	$("form").on ("submit", function (e) {
		sma_name = $("input#sma_name").val();
		sma_names = sma_name.split(" ");
		sma_name_cpt = sma_names.length;
		
		sell_min_margin_cpt = $("input#sell_min_margin_STEPS").val() || 1;
		sell_floor_margin_cpt = $("input#sell_floor_margin_STEPS").val() || 1;
		stop_loss_margin_cpt = $("input#stop_loss_margin_STEPS").val() || 1;
		buy_min_margin_cpt = $("input#buy_min_margin_STEPS").val() || 1;
		buy_floor_margin_cpt = $("input#buy_floor_margin_STEPS").val() || 1;
		re_entry_margin_cpt = $("input#re_entry_margin_STEPS").val() || 1;
		
		combinaison = sma_name_cpt * sell_min_margin_cpt * sell_floor_margin_cpt * stop_loss_margin_cpt
			* buy_min_margin_cpt * buy_floor_margin_cpt * re_entry_margin_cpt;
		
		if (combinaison > 200) {
			return confirm ("ATTENTION ! vous allez lancer " + combinaison + " simulations. \n" + 
				"Etes-vous sûr ?");
		}
		return true;
	});
	
	
});
