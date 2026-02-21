jQuery(document).ready(function($) {
	var $textarea = $('#agenda-content');
	var $overlay = $('#agenda-lock-overlay');
	var $ownerName = $('#lock-owner-name');
	var $status = $('#agenda-status');
	var isEditing = false;

	// 1. Detect Typing/Interaction
	$textarea.on('input focus', function() {
		isEditing = true;
	});

	// If user clicks away, we stop claiming "editing" status immediately? 
	// Let's keep it simple: if they typed recently, they own it.
	// Reset editing flag after 60s of inactivity to allow lock to release?
	var idleTimer;
	$textarea.on('input', function() {
		clearTimeout(idleTimer);
		isEditing = true;
		idleTimer = setTimeout(function() {
			isEditing = false;
		}, 30000); // 30s idle
	});

	// 2. Hook into Heartbeat Send
	// This runs just before WordPress sends data to the server
	$(document).on('heartbeat-send', function(e, data) {
		// Send our status
		data.agenda_check = true;
		data.agenda_is_editing = isEditing;
	});

	// 3. Hook into Heartbeat Receive
	// This runs when the server replies
	$(document).on('heartbeat-tick', function(e, data) {
		if (!data.agenda_status) {
			return;
		}

		if (data.agenda_status === 'locked') {
			// LOCK IT DOWN
			$textarea.prop('disabled', true);
			$overlay.removeClass('hidden');
			$ownerName.text(data.agenda_owner);
			
			// Update content if provided (live-ish sync)
			if (data.agenda_content && $textarea.val() !== data.agenda_content) {
				$textarea.val(data.agenda_content);
			}
		} else {
			// UNLOCK (Owned or Free)
			$textarea.prop('disabled', false);
			$overlay.addClass('hidden');
		}
	});

	// 4. Force a Heartbeat check immediately on load
	// This prevents the "flash" of an editable field if it's actually locked
	wp.heartbeat.connectNow();

	// 5. Save Button Logic
	$('#agenda-save').on('click', function() {
		var content = $textarea.val();
		var $btn = $(this);
		
		$btn.prop('disabled', true).text('Saving...');
		
		$.post(agendaSettings.ajax_url, {
			action: 'save_agenda',
			nonce: agendaSettings.nonce,
			content: content
		}).done(function(response) {
			if (response.success) {
				$btn.text('Saved!');
				setTimeout(function() { $btn.text('Save').prop('disabled', false); }, 2000);
			} else {
				alert('Error: ' + response.data.message);
				$btn.text('Save').prop('disabled', false);
			}
		}).fail(function() {
			alert('Request failed');
			$btn.text('Save').prop('disabled', false);
		});
	});
});