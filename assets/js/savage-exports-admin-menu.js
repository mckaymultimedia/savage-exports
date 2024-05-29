/**
 * CSV API Requests File.
 *
 * @param {number} shipping_exports_page_number Page Count
 *
 * @package Savage-Exports\Assets
 */

/**
 * Fetches csv as per page set in `shipping_exports_page_number`.
 *
 * @param event button reference.
 */
function fetch_csvs( event ) {
	event.disabled = true; // Disable button click.

	// Initialize rest url.
	let rest_url = '';

	// Select rest url as per requirement.
	switch ( settings.current_page ) {
		case 'savage_financial_exports':
			rest_url = settings.get_financial_exports_csv;
			break;
		case 'savage_address_export':
			rest_url = settings.get_shipping_addresses_csv;
			break;
		case 'savage_contest_exports':
			rest_url = settings.get_contest_exports_csv;
			break;
		case 'savage_road_ready_contest_exports':
			rest_url = settings.get_road_ready_contest_exports_csv;
			break;
		default:
			console.error( 'Wrong page slug.' ); // Return if wrong page.
			return;
	}

	// Fetching csv files data.
	fetch(
		rest_url + '?start_after=' + prev_key,
		{
			// Setting required headers.
			headers : {
				'X-WP-Nonce' : settings.nonce
			}
		}
	)
		.then(
			function ( response ) {
				return response.json();
			}
		)
		.then(
			function ( data ) {

				// If success generate UI.
				if ( data.success ) {
					prev_key = data.prev_key; // updating previous key.

					// Generate UI.
					generate_csv_list_ui( data[ 'files_data' ] );

					// If no more files, remove 'Load More' button.
					if ( 0 === data['next_page_length'] ) {
						event.parentElement.innerText = '--- ' + __( 'No More Files', 'savage-exports' ) + ' ---';
					} else {
						// Increasing page number.
						shipping_exports_page_number++;
					}
				}

				// Enable button click.
				event.disabled = false;
			}
		)
		.catch( // Display error.
			function ( error ) {
				event.disabled = false;
				console.error( error );
			}
		);
}

/**
 * Deletes csv file.
 *
 * @param event     button reference.
 * @param file_name name of file.
 * @param file_path path of file w.r.t. s3 bucket.
 */
function delete_csv( event, file_name, file_path ) {
	event.disabled = true; // Disable button click.

	// Initialize rest url.
	let rest_url = settings.delete_csv;

	let confirmed = confirm( 'Do you want to delete ' + file_name + ' ?' );

	if ( ! confirmed ) {
		event.disabled = false;
		return;
	}

	// Deleting csv file.
	fetch(
		rest_url + '?file_path=' + file_path,
		{
			method : 'DELETE',

			// Setting required headers.
			headers : {
				'X-WP-Nonce' : settings.nonce
			}
		},
	)
		.then(
			function ( response ) {
				return response.json();
			}
		)
		.then(
			function ( data ) {
				if ( data.success ) {
					// Center elements.
					event.parentElement.parentElement.style.justifyContent = 'center';

					// Remove row.
					event.parentElement.parentElement.innerText = '--- ' + __( 'Deleted', 'savage-exports' ) + ' ---';

					// Enable button click.
					event.disabled = false;
				}
			}
		)
		.catch( // Display error.
			function ( error ) {
				event.disabled = false;
				console.error( error );
			}
		);
}

/**
 * Generates address exports.
 *
 * @param event button reference.
 */
function generate_address_export( event ) {
	event.disabled = true;

	let message_box = document.getElementById( 'savage-msg-box' ); // To display Message.

	fetch(
		settings.generate_address_exports,
		{
			// Setting required headers.
			headers: {
				'X-WP-Nonce': settings.nonce
			}
		}
	).then(
		function ( response ) {
			return response.json();
		}
	).then(
		function ( data ) {
			if ( ! data.success ) {
				event.disabled        = false;
				message_box.innerText = data.message;
				console.error( data.message );
				return;
			}

			message_box.innerText = data.message;
			event.disabled        = false;
		}
	);
}

/**
 * Generates financial report as per the date range.
 *
 * @param event button reference.
 */
function generate_financial_report( event ) {
	event.disabled = true;

	let start_date = document.getElementById( 'savage-start-date' ).value; // Starting date set in date input.
	let end_date   = document.getElementById( 'savage-end-date' ).value;   // End date set in date input.

	let message_box = document.getElementById( 'savage-msg-box' ); // To display Message.

	// Return if start or end date is empty.
	if ( ! start_date || ! end_date ) {
		event.disabled = false;
		return;
	}

	if(!validateDates()){
		message_box.innerText = "Please select a maximum of three months between the dates.";
		event.disabled  = false;
		return;
	}

	fetch(
		settings.generate_financial_exports + start_date + '/' + end_date,
		{
			// Setting required headers.
			headers: {
				'X-WP-Nonce': settings.nonce
			}
		}
	).then(
		function ( response ) {
			return response.json();
		}
	).then(
		function ( data ) {
			if ( ! data.success ) {
				event.disabled        = false;
				message_box.innerText = data.message;
				console.error( data.message );
				return;
			}

			message_box.innerText = data.message;
			event.disabled        = false;
		}
	);
}

  function validateDates() {
	var startDate = new Date(document.getElementById("savage-start-date").value);
	var endDate = new Date(document.getElementById("savage-end-date").value);

	// Calculate the time difference in milliseconds
	var timeDiff = Math.abs(startDate.getTime() - endDate.getTime());

	// Calculate the threshold for 3 months (in milliseconds)
	var threeMonthsThreshold = 3 * 30 * 24 * 60 * 60 * 1000;

	// Compare the time difference with the threshold
	return timeDiff < threeMonthsThreshold;
  }

/**
 * Generates contest report as per the date range.
 *
 * @param event button reference.
 */
function generate_contest_report( event ) {
	event.disabled = true;

	let start_date = document.getElementById( 'savage-start-date' ).value; // Starting date set in date input.
	let end_date   = document.getElementById( 'savage-end-date' ).value;   // End date set in date input.
	let contest    = document.getElementById( 'savage-contests' ).value;   // Contest whose data needs to be fetched.

	let message_box = document.getElementById( 'savage-msg-box' ); // To display Message.

	// Return if start or end date is empty.
	if ( ! start_date || ! end_date ) {
		event.disabled = false;
		return;
	}

	fetch(
		settings.generate_contest_exports + start_date + '/' + end_date + '/' + contest,
		{
			// Setting required headers.
			headers: {
				'X-WP-Nonce': settings.nonce
			}
		}
	).then(
		function ( response ) {
			return response.json();
		}
	).then(
		function ( data ) {
			if ( ! data.success ) {
				event.disabled        = false;
				message_box.innerText = data.message;
				console.error( data.message );
				return;
			}

			message_box.innerText = data.message;
			event.disabled        = false;
		}
	);
}

/**
 * Generates road ready contest report as per the date range.
 *
 * @param event button reference.
 */
function generate_road_ready_contest_report( event ) {
	event.disabled = true;

	let start_date = document.getElementById( 'savage-start-date' ).value; // Starting date set in date input.
	let end_date   = document.getElementById( 'savage-end-date' ).value;   // End date set in date input.

	let message_box = document.getElementById( 'savage-msg-box' ); // To display Message.

	// Return if start or end date is empty.
	if ( ! start_date || ! end_date ) {
		event.disabled = false;
		return;
	}

	fetch(
		settings.generate_road_ready_contest_exports + start_date + '/' + end_date,
		{
			// Setting required headers.
			headers: {
				'X-WP-Nonce': settings.nonce
			}
		}
	).then(
		function ( response ) {
			return response.json();
		}
	).then(
		function ( data ) {
			if ( ! data.success ) {
				event.disabled        = false;
				message_box.innerText = data.message;
				console.error( data.message );
				return;
			}

			message_box.innerText = data.message;
			event.disabled        = false;
		}
	);
}

/**
 * Creates row UI.
 *
 * @param {object[]} files_data file data.
 */
function generate_csv_list_ui( files_data ) {
	files_data.forEach(
		function ( file_data ) {
			// Fetching main container from document.
			let container = document.getElementById( 'savage-csv-files-container' );

			// Creating parent div to hold all child divs.
			let parent_div = document.createElement( 'div' );

			let file_name_child_div = document.createElement( 'div' ); // File-name wrapper div.
			let buttons_child_div   = document.createElement( 'div' ); // Buttons wrapper div.

			let file_name_paragraph = document.createElement( 'p' ); // File-Name.
			let download_button     = document.createElement( 'a' ); // Download-button.
			let delete_button       = document.createElement( 'button' ); // Delete-button.

			parent_div.classList = [ 'csv-parent-container' ]; // Adding class to parent div.

			file_name_child_div.classList = [ 'csv-file-name-container' ]; // Adding class to File-name wrapper div.

			buttons_child_div.classList = [ 'csv-buttons-container' ]; // Adding class to Download-button wrapper div.

			file_name_paragraph.classList = [ 'csv-file-name' ]; // Adding classes to File-name.
			file_name_paragraph.innerText = file_data[ 'file_name' ]; // Adding data to File-name.

			// Configure download button.
			download_button.classList = [ 'savage-btn' ];
			download_button.innerText = __( 'Download File', 'savage-exports' );
			download_button.href      = file_data['download_link'];
			download_button.target    = '_blank';

			// Regenerate & update download link once expired.
			setInterval(
				function () {
					fetch(
						settings.get_download_link + file_data['file_name'] + '?key_name=' + file_data['file_path'],
						{
							// Setting required headers.
							headers : {
								'X-WP-Nonce' : settings.nonce
							}
						}
					).then(
						function ( response ) {
							return response.json();
						}
					).then(
						function ( data ) {
							if ( ! data.success ) {
								console.error( 'Error fetching download URL for ' + file_data['file_name'] );
								return;
							}

							download_button.href = data.download_link;
						}
					)
				},
				settings.download_link_regen_time * 1000 * 60 // Convert to minutes.
			);

			// Configure delete button.
			delete_button.classList = [ 'csv-delete-btn dashicons dashicons-trash' ];

			// Configure button click.
			delete_button.onclick = function() {
				delete_csv( delete_button, file_data[ 'file_name' ], file_data[ 'file_path' ] );
			};


			var element =  document.getElementById("savage_export_file_name");
			if (typeof(element) != 'undefined' && element != null){
			
				var savage_export_file_name = document.getElementById("savage_export_file_name").value;
				var savage_export_file_flag = document.getElementById("savage_export_file_flag").value;
				console.log('File name = ', savage_export_file_name);
				console.log('File flag = ', savage_export_file_flag);
				if(savage_export_file_name == file_data['file_name']){

				
					console.log('File name Match');
					if(savage_export_file_flag != '0'){
						console.log('File flag != 0');
						file_name_child_div.appendChild( file_name_paragraph ); // Adding File-name to its wrapper div.
						buttons_child_div.appendChild( download_button );       // Adding Download-button to its wrapper div.
						buttons_child_div.appendChild( delete_button );         // Adding Delete-button to its wrapper.
		
						parent_div.appendChild( file_name_child_div ); // Adding File-name wrapper div to its parent div.
						parent_div.appendChild( buttons_child_div ); // Adding Download-button wrapper div to its parent div.
		
						container.appendChild( parent_div ); // Adding parent div to main container.
					}
	
				}else{
					console.log('File name Not Match ');
					file_name_child_div.appendChild( file_name_paragraph ); // Adding File-name to its wrapper div.
					buttons_child_div.appendChild( download_button );       // Adding Download-button to its wrapper div.
					buttons_child_div.appendChild( delete_button );         // Adding Delete-button to its wrapper.
	
					parent_div.appendChild( file_name_child_div ); // Adding File-name wrapper div to its parent div.
					parent_div.appendChild( buttons_child_div ); // Adding Download-button wrapper div to its parent div.
	
					container.appendChild( parent_div ); // Adding parent div to main container.
				}

			}else{
				console.log('File name Not Match ');
				file_name_child_div.appendChild( file_name_paragraph ); // Adding File-name to its wrapper div.
				buttons_child_div.appendChild( download_button );       // Adding Download-button to its wrapper div.
				buttons_child_div.appendChild( delete_button );         // Adding Delete-button to its wrapper.

				parent_div.appendChild( file_name_child_div ); // Adding File-name wrapper div to its parent div.
				parent_div.appendChild( buttons_child_div ); // Adding Download-button wrapper div to its parent div.

				container.appendChild( parent_div ); // Adding parent div to main container.
			}


		}
	);
}

const { __ }                     = wp.i18n;
let prev_key                     = '';
let load_more_button             = document.getElementById( 'savage-csv-files-load-more-button' );
let shipping_exports_page_number = 1;

// Fires when user goes offline.
window.addEventListener(
	'offline',
	function ( event) {
		load_more_button.disabled  = true;
		load_more_button.innerText = __( 'Offline', 'savage-exports' );

		Array.from( document.getElementsByClassName( 'csv-delete-btn' ) ).forEach(
			function ( button ) {
				button.disabled = true;
			}
		);
	}
);

// Fires when user is back online.
window.addEventListener(
	'online',
	function ( event) {
		load_more_button.disabled  = false;
		load_more_button.innerText = __( 'Load More', 'savage-exports' );

		Array.from( document.getElementsByClassName( 'csv-delete-btn' ) ).forEach(
			function( button ) {
				button.disabled = false;
			}
		);
	}
);

// Fetch CSVs.
if ( load_more_button ) {
	fetch_csvs( load_more_button );
}


function generate_address_export_file( event ) {
	// event.disabled = true;
	var address_export = document.getElementById("address_export").value;
	var subscriptionsCount = document.getElementById("subscriptions_count").value;
	var authorEmail = document.getElementById("author_email").value;
	 var dataToSend = {
		user_email: authorEmail,
		total_records: subscriptionsCount,
		fetch_row: 1000,
		offset: 0,
		address_export: address_export,
	};
	
	fetch('https://data.savage.ventures/api/get/subscription', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify(dataToSend),
	})
	.then(function(response) {
		return response.json();
	})
	.then(function(data) {
		console.log(data);
	})
	.catch(function(error) {
		console.error(error);
	});
	
}