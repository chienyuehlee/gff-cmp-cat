$(document).ready(function()
{
	var pid = Sha256.hash(Math.random());
	
	$("#dialog-check-cfg").dialog({
		autoOpen: false,
		show : "slide",
		hide: "slide",
		width: 550,
		modal: true,
		resizable: false,
		buttons: {
			OK: function() {$(this).dialog("close");}
        }
	});
	
	$("#dialog-gff-cmp-cat-cfg").dialog({
		autoOpen: false,
		show : "slide",
		hide: "slide",
		width: 550,
		modal: true,
		resizable: false,
		buttons: {
			OK: function() {$(this).dialog("close");}
        }
	});
	
	$("#div-run-statistics").statistics({
		StatisticsCallback: function (upload_data) {
			//alert(JSON.stringify(data));
			$(this).css({"pointer-events": "none", "background-color": "#bfbfbf"}).after('<div id="div-processing" style="float:right"><img src="../img/processing.gif" />Processing...</div>');
			$(this).parent("div").find("#run-force-chk").attr("disabled", "disabled");
			$(this).parent("div").find("#button-settings").css({"pointer-events": "none", "background-color": "#bfbfbf"});
			$(".ajax-file-upload-red").css({"pointer-events": "none", "background-color": "#bfbfbf"});
			
			obj_ckbox = $("input.cmp-ckbox:checked");
			var ckbox_val = [];
			for(var i=0; i<obj_ckbox.length; i++) {
				ckbox_val[i] = obj_ckbox.eq(i).val();
			}
			var now_time = new Date().getTime()
			$.extend(upload_data, {ckbox: ckbox_val, timestamp: now_time});
			
			var that = $(this);
			
			$.ajax({
				type: "POST",
				url: "../php/gff-cmp-cat.php?pid="+pid,
				data: upload_data,
				success: function (resp,textStatus, jqXHR) {
							//Show Message	
							//$("#statistical-result").html($("#statistical-result").html()+resp);
							var re = new createResultDiv(now_time);
							re.result.html(resp);
							re.button_download.appendTo(re.button_frame);
							$("#tabs-4").append(re.main_frame);
							
							that.parent("div").find("#div-processing").remove();
							that.css({"pointer-events": "auto", "background-color": "#77b55a"});
							that.parent("div").find("#run-force-chk").removeAttr("disabled");
							that.parent("div").find("#button-settings").css({"pointer-events": "auto", "background-color": "#8cb5c0"});
							$(".ajax-file-upload-red").css({"pointer-events": "auto", "background-color": "#e4685d"});
							$("#tabs").tabs("option", "active", 2);
							//alert(resp);
						 },
				error: function(jqXHR, textStatus, errorThrown) {
							that.parent("div").find("#div-processing").remove();
							that.css({"pointer-events": "auto", "background-color": "#77b55a"});
							that.parent("div").find("#run-force-chk").removeAttr("disabled");
							that.parent("div").find("#button-settings").css({"pointer-events": "auto", "background-color": "#8cb5c0"});
							$(".ajax-file-upload-red").css({"pointer-events": "auto", "background-color": "#e4685d"});
							
							alert("Error status: "+textStatus+"\nError thrown: "+errorThrown);
					   },
			});
		}
	});

	$( "#tabs" ).tabs({
		active: 0 // To show the Uploading panel when initialization.
	});
	
	$("#fileuploader1").uploadFile({
		url:"../php/upload.php?pid="+pid,
		multiple:true,
		maxFileCount: 2,
		allowedTypes: "gff,gff3",
		fileName:"myfile",
		dragDropStr: "<span><b>or Drag &amp; Drop Files</b></span>",
		doneStr:"Check GFF", 
		checkCallback: function (upload_data) {
			//$(this).css({"pointer-events": "none", "background-color": "#bfbfbf"}).after('<div id="div-processing" style="float:right"><img src="../img/processing.gif" />Processing...</div>');
			$(this).css({"pointer-events": "none", "background-color": "#bfbfbf"});
			$(this).parent("div").find(".ajax-file-upload-red").last().css({"pointer-events": "none", "background-color": "#bfbfbf"});
			$(this).parent("div").find("#div-settings-btn").css({"pointer-events": "none", "background-color": "#bfbfbf"});
			$(this).parent("div").find("#div-custom").empty();
			$(this).parent("div").find("#div-custom").append('<div id="div-processing" style="float:right"><img src="../img/processing.gif" />Processing...</div>');
			
			var that = $(this);
			obj_ckbox = $("input.qc-ckbox:checked");
			var ckbox_val = [];
			for(var i=0; i<obj_ckbox.length; i++) {
				ckbox_val[i] = obj_ckbox.eq(i).val();
			}
			var now_time = new Date().getTime()
			var stat = new creatStatDiv(now_time);
			
			$(this).parent("div").find("#div-custom").append(stat.main_frame);
			
			$.ajax({
				type: "POST",
				url: "../php/qc.php?pid="+pid,
				data: {gff: upload_data, ckbox: ckbox_val, timestamp: now_time},
				success: function (resp,textStatus, jqXHR) {
							//Show Message	
							//$("#checking-result").html($("#checking-result").html()+resp);
							var json = $.parseJSON(resp);
							var arr_stat_num = {
								pass: json.pass.gene.total+json.pass.pseudogene.total, 
								warning: json.warning.total, 
								fail: json.fail.total
							};
							stat.setCount(arr_stat_num);
							stat.main_frame.show();
							
							if(json.fail.total == 0) {
								$("#div-run-statistics").statistics('SetPassFile', json.filename);
							}
							
							var re = new createResultDiv(now_time);
							re.result.html(makeQCReport(json, now_time));
							$("#tabs-3").append(re.main_frame);
							$( "#accordion-"+now_time ).accordion({
								collapsible: true, 
								heightStyle: "content"
							});
							$( "#tabs-fail-"+now_time ).tabs();
							$( "#tabs-warning-"+now_time ).tabs();
							 
							//$("#checking-result").html(resp);
							that.parent("div").find("#div-processing").remove();
							that.css({"pointer-events": "auto", "background-color": "#77b55a"}).hide();
							that.parent("div").find(".ajax-file-upload-red").last().css({"pointer-events": "auto", "background-color": "#e4685d"});
							that.parent("div").find("#div-settings-btn").css({"pointer-events": "auto", "background-color": "#8cb5c0"}).hide();
							
							//$("#tabs").tabs("option", "active", 2);
							//alert(resp);
						},
				error: function(jqXHR, textStatus, errorThrown) {
							$("#div-processing").remove();
							that.css({"pointer-events": "auto", "background-color": "#77b55a"});
							that.parent("div").find(".ajax-file-upload-red").last().css({"pointer-events": "auto", "background-color": "#e4685d"});
							that.parent("div").find("#div-settings-btn").css({"pointer-events": "auto", "background-color": "#8cb5c0"}).hide();

							alert("Error status: "+textStatus+"\nError thrown: "+errorThrown);
					   },
			});
		
		},
		
		deleteCallback: function (data, pd) {
			$.post("../php/delete.php?pid="+pid, {op: "delete",name: data},
			function (resp,textStatus, jqXHR) {
				//Show Message
				//alert(resp);
			});
				 
			$("#div-run-statistics").statistics('SetNumFile', $(".ajax-file-upload-filename").length);
			$("#div-run-statistics").statistics('DelRadio', data.replace(/\["(.+)"\]/g, '$1'));
		},
		
		settingsCallback: function () {
			$("#dialog-check-cfg").dialog({
				position: { 
					my: "left center", 
					at: "right", 
					of: $(this)
				}
			}).dialog("open");
			
		},

		onSubmit:function(files)
		{
			//$("#eventsmessage").html($("#eventsmessage").html()+"<br/>Submitting:"+JSON.stringify(files));
		},
		onSuccess:function(files,data,xhr,pd)
		{
			$("#div-run-statistics").statistics('SetRadio', files);
			
			//$("#eventsmessage").html($("#eventsmessage").html()+"<br/>Success for: "+JSON.stringify(data));		
		},
		afterUploadAll:function()
		{
			//$("#num_files").val($(".ajax-file-upload-filename").length).change(); //Getting the numbers of files
			$("#div-run-statistics").statistics('SetNumFile', $(".ajax-file-upload-filename").length);
			
			//$("#eventsmessage").html($("#eventsmessage").html()+"<br/>All files are uploaded");
		},
		onError: function(files,status,errMsg)
		{
			//$("#eventsmessage").html($("#eventsmessage").html()+"<br/>Error for: "+JSON.stringify(files));
		}
	});
	
	$("#qc-ckbox-all").change(function () {
		var is_checked = $(this).is(":checked");
		$(".qc-ckbox").each(function() {
			$(this).prop("checked", is_checked).attr("checked", is_checked);
		});
	});
	
	$("#cmp-ckbox-all").change(function () {
		var is_checked = $(this).is(":checked");
		$(".cmp-ckbox").each(function() {
			$(this).prop("checked", is_checked).attr("checked", is_checked);
		});
	});
	
	function createResultDiv(now_time) {
		this.main_frame = $('<div class="div-result-framework" id="div-result-framework-'+now_time+'"></div>');
		this.button_frame = $('<div class="div-button-framework" id="div-button-framework-'+now_time+'"></div>').appendTo(this.main_frame);
		this.button_close = $('<div class="div-button-close" id="div-button-close-'+now_time+'"><span class="ui-icon ui-icon-closethick"></span></div>').appendTo(this.main_frame);
		this.result = $('<div class="div-result" id="div-result-'+now_time+'"></div>').appendTo(this.main_frame);
		this.button_download = $('<div class="div-button-green" id="button-download-'+now_time+'">Detailed results download</div>');
		
		var obj = this;		
		this.button_close.click(function() {
			if(confirm("You are about to close the result window. Are you sure to continue?"))
			{
				$(obj.main_frame).remove();
			}
		});
		
		this.button_download.click(function() {
			location.href= '../php/download.php?pid='+pid+'&filename='+now_time;
		});
	
		return this;
	};
	
	function creatStatDiv(now_time) {
		this.main_frame = $('<div class="css-table div-stat-framework" id="div-stat-framework-'+now_time+'"></div>').hide();
		this.tr = $('<div class="css-tr"></div>').appendTo(this.main_frame);
		this.pass_img = $('<div class="css-td-stat-img" title="pass feature(s)"><img src="../img/pass.png" /></div>').appendTo(this.tr);
		this.pass_cell = $('<div class="css-td-stat-word" id="pass_cell" title="pass feature(s)"></div>').appendTo(this.tr);
		this.warning_img = $('<div class="css-td-stat-img" title="warning feature(s)"><img src="../img/warning.png" /></div>').appendTo(this.tr);
		this.warning_cell = $('<div class="css-td-stat-word" id="warning_cell" title="warning feature(s)"></div>').appendTo(this.tr);
		this.fail_img = $('<div class="css-td-stat-img" title="failed feature(s)"><img src="../img/fail.png" /></div>').appendTo(this.tr);
		this.fail_cell = $('<div class="css-td-stat-word" id="fail_cell" title="failed feature(s)"></div>').appendTo(this.tr);
		
		var obj = this;
		
		this.setCount = function(arr_stat_num) {
			obj.pass_cell.html(arr_stat_num['pass']);
			obj.warning_cell.html(arr_stat_num['warning']);
			obj.fail_cell.html(arr_stat_num['fail']);
		};
			
		this.pass_img.click(function() {
			$("#accordion-"+now_time).accordion( "option", "active", 0 );
			$("#tabs").tabs("option", "active", 1);
			var link = $('<a href="#link-'+now_time+'"></a>').click();
			window.location = link.attr("href");
		});
		this.pass_cell.click(function() {
			$("#accordion-"+now_time).accordion( "option", "active", 0 );
			$("#tabs").tabs("option", "active", 1);
			var link = $('<a href="#link-'+now_time+'"></a>').click();
			window.location = link.attr("href");
		});
		this.warning_img.click(function() {
			if(obj.warning_cell.html() == '0') {return;}
			
			$("#accordion-"+now_time).accordion( "option", "active", 1 );
			$("#tabs").tabs("option", "active", 1);
			var link = $('<a href="#link-'+now_time+'"></a>').click();
			window.location = link.attr("href");
		});
		this.warning_cell.click(function() {
			if(obj.warning_cell.html() == '0') {return;}
			
			$("#accordion-"+now_time).accordion( "option", "active", 1 );
			$("#tabs").tabs("option", "active", 1);
			var link = $('<a href="#link-'+now_time+'"></a>').click();
			window.location = link.attr("href");
		});
		this.fail_img.click(function() {
			if(obj.fail_cell.html() == '0') {return;}
			
			$("#accordion-"+now_time).accordion( "option", "active", $("#accordion-"+now_time+" h3").length-1 );
			$("#tabs").tabs("option", "active", 1);
			var link = $('<a href="#link-'+now_time+'"></a>').click();
			window.location = link.attr("href");
		});
		this.fail_cell.click(function() {
			if(obj.fail_cell.html() == '0') {return;}
			
			$("#accordion-"+now_time).accordion( "option", "active", $("#accordion-"+now_time+" h3").length-1 );
			$("#tabs").tabs("option", "active", 1);
			var link = $('<a href="#link-'+now_time+'"></a>').click();
			window.location = link.attr("href");
		});
	}
	
	function makeQCReport(json, now_time) {
		//var json = $.parseJSON(data);
		var str_return;

		// Show file name
		str_return = '<a name="link-'+now_time+'">File name: <strong>'+json.filename+'</strong></a><br/>';
		
		// Show passed features on the accordion
		str_return += '<div id="accordion-'+now_time+'"><h3>Passed feature(s): '+(json.pass.gene.total+json.pass.pseudogene.total)+'</h3>';

		// Gene summary
		str_return += '<div>Gene Summary: (Passed features: '+json.pass.gene.total+')<br/>------------------------Gene------------------------<br/>';
		
		$.each( json.pass.gene, function( key, value ) {
			if(key == 'total') {return;}
			str_return += key+': '+value+ '<br/>';
		});
		
		//Pseudogene summary
		if(json.pass.pseudogene.total > 0)
		{
			str_return += '<br/>Pseudogene Summary: (Passed features: '+json.pass.pseudogene.total+')<br/>---------------------Pseudogene---------------------<br/>';
			
			$.each( json.pass.pseudogene, function( key, value ) {
				if(key == 'total') {return;}
				str_return += key+': '+value+ '<br/>';
			});
		}
		str_return += '</div>';	// End passed features on the accordion
		
		//Show warning features
		if(json.warning.total > 0)
		{
			var warning_tip = {
				zero_start: " feature(s) with a start coordinate of 0.", 
				unstranded: " feature(s) contain unknown strandedness."
			};
			
			// Show warning features on the accordion
			str_return += '<h3>Warning feature(s): '+json.warning.total+'</h3>';			
			str_return += '<div>';
			
				// Generate a Tab UI
				str_return += '<div id="tabs-warning-'+now_time+'">';
					str_return += '<ul>';
					$.each( json.warning, function(warning_type, arr_err_msgs) {
						if(warning_type == 'total') {return;}
						if(arr_err_msgs.length == 0) {return;}
						str_return += '<li><a href="#tabs-'+warning_type+'-'+now_time+'">'+warning_type.replace(/_/g, " ")+'</a></li>';
					});
					str_return += '</ul>';
					
					$.each( json.warning, function(warning_type, arr_err_msgs) {
						if(warning_type == 'total') {return;}
						if(arr_err_msgs.length == 0) {return;}
						str_return += '<div id="tabs-'+warning_type+'-'+now_time+'">';
						str_return += '<div class="div-warning-tip"><strong>'+arr_err_msgs.length+warning_tip[warning_type]+'</strong></div>';
						for(var i=0; i<arr_err_msgs.length; i++) {
							str_return += '<div class="div-warning-msg">'+arr_err_msgs[i]+'</div>';
						}
						
						str_return += '</div>';
					});
					
				str_return += '</div>';

			str_return += '</div>'; // End warning features on the accordion
		}
		
		//Show fail features
		if(json.fail.total > 0)
		{
			var fail_tip = {
				redundant: " feature(s) with identical values from column 1 to 8.", 
				negative_coordinate: " feature(s) with negative coordinates.", 
				coordinate_boundary: " child feature(s)' coordinates exceed those of their parent feature.", 
				redundant_length: " parent gene(s) have child mRNA features that do not comprise the entire length of the gene(s).", 
				mRNA_in_pseudogene: " of mRNA features that have a pseudogene parent.", 
				incomplete: " of gene features without any child features (e.g. mRNA, exon, CDS)."
			};
			
			// Show fail features on the accordion
			str_return += '<h3>Failed feature(s): '+json.fail.total+'</h3>';			
			str_return += '<div>';
			
				// Generate a Tab UI
				str_return += '<div id="tabs-fail-'+now_time+'">';
					str_return += '<ul>';
					$.each( json.fail, function(fail_type, arr_err_msgs) {
						if(fail_type == 'total') {return;}
						if(arr_err_msgs.length == 0) {return;}
						str_return += '<li><a href="#tabs-'+fail_type+'-'+now_time+'">'+fail_type.replace(/_/g, " ")+'</a></li>';
					});
					str_return += '</ul>';
					
					$.each( json.fail, function(fail_type, arr_err_msgs) {
						if(fail_type == 'total') {return;}
						if(arr_err_msgs.length == 0) {return;}
						str_return += '<div id="tabs-'+fail_type+'-'+now_time+'">';
						str_return += '<div class="div-fail-tip"><strong>'+arr_err_msgs.length+fail_tip[fail_type]+'</strong></div>';
						for(var i=0; i<arr_err_msgs.length; i++) {
							str_return += '<div class="div-fail-msg">'+arr_err_msgs[i]+'</div>';
						}
						
						str_return += '</div>';
					});
					
				str_return += '</div>';

			str_return += '</div>'; // End failed features on the accordion
		}
		
		return str_return+'</div>';	// Complete the accordion
	}
});

(function($) {

	var btn = $("#button-run-statistics");
	var origin_radio1 = $('<div class="css-td"><input type="radio" class="rad-gff-file" id="origin_radio1" name="gff_origin" checked="checked" /></div>');
	var curated_radio1 = $('<div class="css-td"><input type="radio" class="rad-gff-file" id="curated_radio1" name="gff_curated" /></div>');
	var origin_radio2 = $('<div class="css-td"><input type="radio" class="rad-gff-file" id="origin_radio2" name="gff_origin" /></div>');
	var curated_radio2 = $('<div class="css-td"><input type="radio" class="rad-gff-file" id="curated_radio2" name="gff_curated" checked="checked" /></div>');

	var header_div = $('<div class="css-th"><div class="css-td">File name</div><div class="css-td">Original model</div><div class="css-td">Curated file</div></div>');
	var first_file_div = $('<div class="css-tr"><div class="div-upload-file-name css-td"></div></div>').append(origin_radio1).append(curated_radio1);
	var second_file_div = $('<div class="css-tr"><div class="div-upload-file-name css-td"></div></div>').append(origin_radio2).append(curated_radio2);
	$('#radio-run-statistics').append(header_div);
	$('#radio-run-statistics').append(first_file_div);
	$('#radio-run-statistics').append(second_file_div);
	
	var run_force_confirm_div = $('<div id="div-run-force-confirm"></div>').hide().insertAfter(btn);
	var run_force_confirm_chk = $('<input type="checkbox" id="run-force-chk" /><label for="run-force-chk">Force to run gff-cmp-cat</label>').appendTo(run_force_confirm_div);
	
	var btn_settings = $('<div class="div-button-blue" id="button-settings">Settings</div>').insertAfter(run_force_confirm_div);
	
	var obj_rad_gff_file = $(".rad-gff-file");
	var obj_label = $(".div-upload-file-name");
	
	run_force_confirm_chk.change(function() {
		if($(this).is(":checked")) {
			btn.css({"pointer-events": "auto", "background-color": "#77b55a"}).removeAttr('title');
			
			alert("Warning: The uploaded gff files are not checked before running gff-cmp-cat, or some failed features are found.\n\nThe results should NOT be trusted while to run gff-cmp-cat with any failed features.");		
		}
		else {
			btn.css({"pointer-events": "none", "background-color": "#bfbfbf"}).attr('title', 'Not all GFF files are pass checking.');
		}
	});
	
	obj_rad_gff_file.change(function() {
		/*------------------------------------------------------
		obj_rad_gff_file.eq(0).attr("id") = origin_radio1
		obj_rad_gff_file.eq(1).attr("id") = curated_radio1
		obj_rad_gff_file.eq(2).attr("id") = origin_radio2
		obj_rad_gff_file.eq(3).attr("id") = curated_radio2
		When origin_radio1 is clicked, the curated_radio2 would also be checked, vice versa. (Let X=index(0), Y=index(3))
		Likewise, origin_radio2 is clicked, the curated_radio1 would also be checked, vice versa. (Let X=index(1), Y=index(2))
		It can be summarized as the equation: Y = -X + 3
		------------------------------------------------------*/
		var current_inx = obj_rad_gff_file.index(this);
		var corresponding_idx = (-1)*current_inx+3;
		obj_rad_gff_file.removeAttr("checked");
		obj_rad_gff_file.eq(current_inx).prop("checked", true).attr("checked", true);
		obj_rad_gff_file.eq(corresponding_idx).prop("checked", true).attr("checked", true);
		
		post_files[obj_rad_gff_file.eq(current_inx).attr("name")] = obj_rad_gff_file.eq(current_inx).attr("value");
		post_files[obj_rad_gff_file.eq(corresponding_idx).attr("name")] = obj_rad_gff_file.eq(corresponding_idx).attr("value");

	});
	
	var upload_files = [];
	var opts = {};
	var post_files = {};
	var pass_checking = [];
	
	var methods = {
		init : function(options) {
			var s = $.extend({
				StatisticsCallback: false
			}, options);
			
			opts = s;
			btn.click(function () {
				opts.StatisticsCallback.call(this, post_files);
			});
			btn_settings.click(function () {
				$("#dialog-gff-cmp-cat-cfg").dialog({
					position: { 
						my: "left center", 
						at: "right", 
						of: $(this)
					}
				}).dialog("open");
			});
		},
		
		SetNumFile : function(NumFile) {
			if(NumFile == 2){
				btn.css({"pointer-events": "none", "background-color": "#bfbfbf"}).attr('title', 'Not all GFF files are pass checking.');
				run_force_confirm_chk.removeAttr("checked");
				run_force_confirm_div.show();
				this.attr('style', "display:block;");
								
			}
			else {
				this.attr('style', "display:none;");
			}
		},
		
		SetRadio : function(FileName) {
			upload_files.push(FileName);
			if(upload_files.length == 2) {
				obj_rad_gff_file.eq(0).attr("value", upload_files[0]);
				//obj_label.eq(0).html(upload_files[0]);
				obj_rad_gff_file.eq(1).attr("value", upload_files[0]);
				//obj_label.eq(2).html(upload_files[0]);
				obj_label.eq(0).text(upload_files[0]);
				obj_rad_gff_file.eq(2).attr("value", upload_files[1]);
				//obj_label.eq(1).html(upload_files[1]);
				obj_rad_gff_file.eq(3).attr("value", upload_files[1]);
				//obj_label.eq(3).html(upload_files[1]);
				obj_label.eq(1).text(upload_files[1]);
				
				obj_rad_gff_file.eq(0).prop("checked", true).attr("checked", true);
				obj_rad_gff_file.eq(3).prop("checked", true).attr("checked", true);
				post_files[obj_rad_gff_file.eq(0).attr("name")] = obj_rad_gff_file.eq(0).attr("value");
				post_files[obj_rad_gff_file.eq(3).attr("name")] = obj_rad_gff_file.eq(3).attr("value");
			}
		},
		
		DelRadio : function(FileName) {
			obj_label.each(function() {
				if($(this).text() == FileName) {
					$(this).text("");
				}
			});
			
			for(var key in post_files) {
				if(post_files[key] == FileName) {
					post_files[key] = "";
				}
			}
			
			for(var i=0; i<upload_files.length; i++) {
				if(upload_files[i] == FileName) {
					upload_files.splice(i,1);
				}
			}
			
			for(var i=0; i<pass_checking.length; i++) {
				if(pass_checking[i] == FileName) {
					pass_checking.splice(i,1);
				}
			}
		},
		
		SetPassFile : function(FileName) {
			pass_checking.push(FileName);
			
			if(pass_checking.length == 2) {
				btn.css({"pointer-events": "auto", "background-color": "#77b55a"}).removeAttr('title');
				run_force_confirm_div.hide();
			}
		}
		
	};	
	
	
	$.fn.statistics = function(method) {
		// Method calling logic
		if (methods[method]) {
			return methods[method].apply( this, Array.prototype.slice.call(arguments, 1));
		} else if (typeof method === 'object' || ! method) {
			return methods.init.apply(this, arguments);
		} else {
			//$.error( 'Method ' +  method + ' does not exist on jQuery.tooltip' );
		}   
	};

})(jQuery);
