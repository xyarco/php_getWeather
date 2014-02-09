function checkForm(form){
	re=/^\d{5}$/;
	
	if(form.locationType.value == "City"){
		if(form.location.value.length == 0){
			alert("Please enter a location!");
			return false;
		}else{
			return true;
		}
	}else{
		if(!re.test(form.location.value)){
			alert("Please enter a valid zip code!");
		}

		return re.test(form.location.value);
	}
}