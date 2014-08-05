function subme_cron_enabled ($val) {
	if ($val == 1) {
		document.getElementById("subme_confirmation_via_queue").disabled = false;
		document.getElementById("subme_cron_minutes").disabled = false;
		document.getElementById("subme_emails_per_burst").disabled = false;
	} else {
		document.getElementById("subme_confirmation_via_queue").disabled = true;
		document.getElementById("subme_cron_minutes").disabled = true;
		document.getElementById("subme_emails_per_burst").disabled = true;
	}
}

function subme_cb_toggle () {
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
