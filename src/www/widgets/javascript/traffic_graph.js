function trafficshowDiv(incDiv,swapButtons){
	//appear element
	selectedDiv = incDiv + "graphdiv";
	jQuery('#' + selectedDiv).show();      
	d = document;	
	if (swapButtons){
		selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";	    
		
		selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";
	}
}
	
function  trafficminimizeDiv(incDiv,swapButtons){
	//fade element
	selectedDiv = incDiv + "graphdiv";
	jQuery('#' + selectedDiv).hide(); 
	d = document;	
	if (swapButtons){
		selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";	    
		
		selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";
	} 
}

