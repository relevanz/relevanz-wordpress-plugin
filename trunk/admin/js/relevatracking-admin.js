;(function (window, $, undefined) {
	'use strict';


    $.fn.conversions = function(options) {

            /*variables*/
            var dateObject;
			
            var apiKey ;
			if (options.apikey) {
				apiKey = options.apikey;
				localStorage.setItem('userAPI', apiKey);
			}else {
			    apiKey = localStorage.getItem('userAPI');	
			}

            var startDate,endDate;

			//fixed by oppo webiprog.com
			var $this = this;
            //user key get
			if (!options.apikey)
			{
			user_key_get();
			}
			else 
			{
				getData(startDate, endDate, apiKey);
			}


			function ResizeIframeFromParent(id) {
				if (jQuery('#'+id).length > 0) {
					var window = document.getElementById(id).contentWindow;
					var prevheight = jQuery('#'+id).attr('height');
					var newheight = Math.max( window.document.body.scrollHeight, window.document.body.offsetHeight, window.document.documentElement.clientHeight, window.document.documentElement.scrollHeight, window.document.documentElement.offsetHeight );

					console.log("iframe height for "+id+": " +prevheight+"px => "+newheight+"px");

					if (newheight != prevheight && newheight > 0) {
						jQuery('#'+id).attr('height', newheight);
						console.log("Adjusting iframe height for "+id+": " +prevheight+"px => "+newheight+"px");
					}
				}
			}
			
        
                function getData(startDate, endDate, apiKey) {

				 var apiKey = localStorage.getItem('userAPI');

                   var getUrl = "https://frontend.releva.nz/token?token=" + apiKey;
				    //$('#RelevaChart').load(getUrl);
					$("#gopolegelcontent").attr("src" , getUrl );

					var newheight = 2074;
					//var wrapheight = jQuery('#RelevaWrap').height();
					var wrapheight = $(document).height();
					$("#gopolegelcontent").attr('height', wrapheight);

		
					/*
					$("#gopolegelcontent").iframeAutoHeight({
					  debug: true,
					  minHeight: 1180,
					  diagnostics: true,
					  heightOffset: 40
					});
					*/
					
					/*
					setInterval(function() {
						ResizeIframeFromParent('gopolegelcontent');
					}, 2000);
					*/
					
					
                    
                }

            //user key get
			function user_key_get() {
			$this.append('<div id="dialogBlock">'+
                            '<p id="dialogMess">'+relevatracking_opt.dialog_received+'</p>' +
                            '<p style="color:#ff0033" id="dialogHidden"">'+relevatracking_opt.dialog_invalid+'</p>' +
                            '<input type="text" value="" id="userKeyInput"/><br />'+
                            '<br />'+relevatracking_opt.dialog_register+''+
                        '</div>');

            $( "#dialogBlock" ).dialog({
                width: "35%",
                open: function() {
                    // On open, hide the original submit button and error message
                    $( $this ).find( "[type=submit]" ).hide();
                    $( "#dialogHidden" ).hide();
                },
                buttons: [
                    {
                        text: relevatracking_opt.dialog_ok,
                        click: function() {
                            var userAPI = $('#userKeyInput').val();
							userAPI = $.trim(userAPI);
                            var userKeyURL = "https://backend.releva.nz/v1/campaigns/get?apikey=" + userAPI;
                            $.ajax({
                                url: userKeyURL,
                                type: 'GET',
                                crossDomain: true,
                                dataType: 'json',
                                success: function (data) {
									console.log("Get apikey");
                                    //get data test
									if (typeof(data) == "object" && data.user_id && data.user_id != "") {
									console.log("user_id : " + data.user_id);
									var user_id = data.user_id;

								  var data_api = {
										action: 'add_apikey_settings',
										client_id: user_id,
										api_key: userAPI,
									};
									
									$.post(relevatracking_opt.ajaxurl, data_api, function(response) {
										console.log("add_apikey_settings : " + response);
										
										if (response=='1') {
											var mess = relevatracking_opt.settings_saved;
										}else {
											var mess = relevatracking_opt.settings_error;
										}
										$('#dialogBlock').html(mess);
										//.hide(2000)
									});

                                    localStorage.setItem('userAPI', userAPI);
									//localStorage.setItem('userAPI', options.apikey)

									options.apikey = userAPI;
									var apiKey  = userAPI;
                                    //$("#dialogBlock").dialog( "close" );
									setTimeout(function(){
										$("#dialogBlock").dialog('close')
									}, 2000);

                                    //getData(startDate, endDate, apiKey);
									getData(startDate, endDate, userAPI);
									}else {
									console.log("Error get apikey : " + data);
                                    $("#dialogBlock").find( "#dialogHidden" ).show();	
									}
                                },
                                error: function (errorThrown) {
									console.log(errorThrown);
                                    $("#dialogBlock").find( "#dialogHidden" ).show();
                                }
                            });
                        }
                    }
                ]
            });
			}
    }
})(window, jQuery);
