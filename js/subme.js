function subme_cron_enabled($val) {
	if ($val == 1) {
		document.getElementById("subme_confirmation_via_queue_yes").disabled = false;
		document.getElementById("subme_confirmation_via_queue_no").disabled = false;
		document.getElementById("subme_manage_via_queue_yes").disabled = false;
		document.getElementById("subme_manage_via_queue_no").disabled = false;
		document.getElementById("subme_cron_minutes").disabled = false;
		document.getElementById("subme_emails_per_burst").disabled = false;
	} else {
		document.getElementById("subme_confirmation_via_queue_yes").disabled = true;
		document.getElementById("subme_confirmation_via_queue_no").disabled = true;
		document.getElementById("subme_manage_via_queue_yes").disabled = true;
		document.getElementById("subme_manage_via_queue_no").disabled = true;
		document.getElementById("subme_cron_minutes").disabled = true;
		document.getElementById("subme_emails_per_burst").disabled = true;
	}
}

function subme_cb_toggle() {
	var check=false;
	checkboxes = document.querySelectorAll("input[name^='cb[']");
	for (var i=0, n=checkboxes.length; i < n; i++) {
		if (!checkboxes[i].checked) {
			check = true;
		}
	}

	for (var i=0, n=checkboxes.length; i < n; i++) {
		if (check) {
			checkboxes[i].checked = true;
		} else {
			checkboxes[i].checked = false;
		}
	}
}

function subme_authors_enable() {
	checkboxes = document.querySelectorAll("input[name^='allowed_authors[']");
	
	for (var i=0, n=checkboxes.length; i < n; i++) {
		if (document.getElementById("subme_all_authors").checked) {
			checkboxes[i].disabled = true;
		} else {
			checkboxes[i].disabled = false;
		}
	}
}

function subme_categories_enable() {
	checkboxes = document.querySelectorAll("input[name^='allowed_categories[']");
	
	for (var i=0, n=checkboxes.length; i < n; i++) {
		if (document.getElementById("subme_all_categories").checked) {
			checkboxes[i].disabled = true;
		} else {
			checkboxes[i].disabled = false;
		}
	}
}

function subme_manage_authors_enable() {
	checkboxes = document.querySelectorAll("input[name^='subme_selected_authors[']");
	
	for (var i=0, n=checkboxes.length; i < n; i++) {
		if (document.getElementById("subme_manage_authors_all").checked) {
			checkboxes[i].disabled = true;
		} else {
			checkboxes[i].disabled = false;
		}
	}
}

function subme_manage_categories_enable() {
	checkboxes = document.querySelectorAll("input[name^='subme_selected_categories[']");
	
	for (var i=0, n=checkboxes.length; i < n; i++) {
		if (document.getElementById("subme_manage_categories_all").checked) {
			checkboxes[i].disabled = true;
		} else {
			checkboxes[i].disabled = false;
		}
	}
}
