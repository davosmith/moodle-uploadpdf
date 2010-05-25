var currentcomment = null;	// The comment that is currently being edited
var editbox = null;		// The edit box that is currently displayed
var resizing = false;		// A box is being resized (so disable dragging)
var server = null;		// The object use to send data back to the server
var context_quicklist = null;
var context_comment = null;
var quicklist = null; // Stores all the comments in the quicklist
var pagelist = null; // Stores all the data for the preloaded pages
var waitingforpage = -1;  // Waiting for this page from the server - display as soon as it is received
var pagestopreload = 4; // How many pages ahead to load when you hit a non-preloaded page
var pagesremaining = pagestopreload; // How many more pages to preload before waiting
var pageunloading = false;

// All to do with line drawing
var currentpaper = null;
var currentline = null;
var linestartpos = null;
var lineselect = null;
var lineselectid = null;
var allannotations = new Array();

var ServerComm = new Class({
	Implements: [Events],
	id: null,
	userid: null,
	pageno: null,
	sesskey: null,
	url: null,
	js_navigation: true,
	retrycount: 0,
	
	initialize: function(settings) {
	    this.id = settings.id;
	    this.userid = settings.userid;
	    this.pageno = settings.pageno;
	    this.sesskey = settings.sesskey;
	    this.url = settings.updatepage;
	    this.js_navigation = settings.js_navigation;
	},
	
	updatecomment: function(comment) {
	    var waitel = new Element('div');
	    waitel.set('class', 'wait');
	    comment.adopt(waitel);
	    comment.store('oldcolour', comment.retrieve('colour'));
	    var request = new Request.JSON({
		    url: this.url,
		    
		    onSuccess: function(resp) {
			server.retrycount = 0;
			waitel.destroy();

			if (resp.error == 0) {
			    comment.store('id', resp.id);
			    //setcommentcontent(comment, resp.text);
			    // Re-attach drag and resize ability
			    comment.retrieve('drag').attach();
			} else {
			    if (confirm(server_config.lang_errormessage+resp.errmsg+'\n'+server_config.lang_okagain)) {
				server.updatecomment(comment);
			    } else {
				// Re-attach drag and resize ability
				comment.retrieve('drag').attach();
			    }
			}
		    },
		    
		    onFailure: function(req) {
			waitel.destroy();
			showsendfailed(function() {server.updatecomment(comment);});
			// TODO The following should really be on the 'cancel' (but probably unimportant)
			comment.retrieve('drag').attach();
		    }

		});

	    request.send({
		    data: {
		        action: 'update',
			comment_position_x: comment.getStyle('left'),
			comment_position_y: comment.getStyle('top'),
			comment_width: comment.getStyle('width'),
			comment_text: comment.retrieve('rawtext'),
    		        comment_id: comment.retrieve('id'),
			comment_colour: comment.retrieve('colour'),
			id: this.id,
			userid: this.userid,
			pageno: this.pageno,
			sesskey: this.sesskey
			    }
		});
	},
	
	removecomment: function(cid) {
	    var request = new Request.JSON({
		    url: this.url,
		    onSuccess: function(resp) {
			server.retrycount = 0;
			if (resp.error != 0) {
			    if (confirm(server_config.lang_errormessage+resp.errmsg+'\n'+server_config.lang_okagain)) {
				server.removecomment(cid);
			    }
			}
		    },
		    onFailure: function(resp) {
			showsendfailed(function() {server.removecomment(cid);} );
		    }
		});

	    request.send({
		    data: {
  			    action: 'delete',
			    commentid: cid,
			    id: this.id,
			    userid: this.userid,
			    pageno: this.pageno,
			    sesskey: this.sesskey
			    }
		});
	},
	
	getcomments: function() {
	    var waitel = new Element('div');
	    waitel.set('class', 'pagewait');
	    $('pdfholder').adopt(waitel);

	    var pageno = this.pageno;
	    var request = new Request.JSON({
		    url: this.url,

		    onSuccess: function(resp) {
			server.retrycount = 0;
			waitel.destroy();
			if (resp.error == 0) {
			    if (pageno == server.pageno) { // Make sure the page hasn't changed since we sent this request
				//$('pdfholder').getElements('div').destroy(); // Destroy all the currently displayed comments (just in case!) - this turned out to be a bad idea
				resp.comments.each(function(comment) {
					cb = makecommentbox(comment.position, comment.text, comment.colour);
					if (Browser.Engine.trident) {
					    // Does not work with FF & Moodle
					    cb.setStyle('width',comment.width);
					} else {
					    // Does not work with IE
					    var style = cb.get('style')+' width:'+comment.width+'px;';
					    cb.set('style',style);
					}

					cb.store('id', comment.id);
				    });

				// Get annotations at the same time
				allannotations.each(function(p) {p.remove()});
				allannotations.empty();
				resp.annotations.each(function(annotation) {
					if (annotation.type == 'line') {
					    var coords = {
						sx: annotation.coords.startx.toInt(),
						sy: annotation.coords.starty.toInt(),
						ex: annotation.coords.endx.toInt(),
						ey: annotation.coords.endy.toInt()
					    };
					    makeline(coords, annotation.id, annotation.colour);
					}
				    });
			    }
			} else {
			    if (confirm(server_config.lang_errormessage+resp.errmsg+'\n'+server_config.lang_okagain)) {
				server.getcomments();
			    }
			}
		    },

		    onFailure: function(resp) {
			showsendfailed(function() {server.getcomments();});
			// TODO The following should be on the 'cancel' button (but only a minor visual bug, rarely seen)
			waitel.destroy();
		    }
		});

	    request.send({ data: {
			    action: 'getcomments',
			    id: this.id,
			    userid: this.userid,
			    pageno: this.pageno,
			    sesskey: this.sesskey
			    } });
	},

	getquicklist: function() {
	    var request = new Request.JSON({
		    url: this.url,

		    onSuccess: function(resp) {
			server.retrycount = 0;
			if (resp.error == 0) {
			    resp.quicklist.each(addtoquicklist);  // Assume contains: id, rawtext, colour, width
			} else {
			    if (confirm(server_config.lang_errormessage+resp.errmsg+'\n'+server_config.lang_okagain)) {
				server.getquicklist();
			    }
			}
		    },

		    onFailure: function(resp) {
			showsendfailed(function() { server.getquicklist(); });
		    }
		});

	    request.send({ data: {
		            action: 'getquicklist',
			    id: this.id,
			    userid: this.userid, // This and pageno are not strictly needed, but are checked for on the server
			    pageno: this.pageno,
			    sesskey: this.sesskey
			    } });
	},

	addtoquicklist: function(element) {
	    var request = new Request.JSON({
		    url: this.url,

		    onSuccess: function(resp) {
			server.retrycount = 0;
			if (resp.error == 0) {
			    addtoquicklist(resp.item);  // Assume contains: id, rawtext, colour, width
			} else {
			    if (confirm(server_config.lang_errormessage+resp.errmsg+'\n'+server_config.lang_okagain)) {
				server.addtoquicklist(element);
			    }
			}
		    },
		    
		    onFailure: function(resp) {
			showsendfailed(function() { server.addtoquicklist(element); });
		    }
		});

	    request.send({ data: {
			    action: 'addtoquicklist',
			    colour: element.retrieve('colour'),
			    text: element.retrieve('rawtext'),
			    width: element.getStyle('width').toInt(),
			    id: this.id,
			    userid: this.userid, // This and pageno are not strictly needed, but are checked for on the server
			    pageno: this.pageno,
			    sesskey: this.sesskey
			    } });
	},

	removefromquicklist: function(itemid) {
	    var request = new Request.JSON({
		    url: this.url,
		    onSuccess: function(resp) {
			server.retrycount = 0;
			if (resp.error == 0) {
			    removefromquicklist(resp.itemid);
			} else {
			    if (confirm(server_config.lang_errormessage+resp.errmsg+'\n'+server_config.lang_okagain)) {
				server.removefromquicklist(itemid);
			    }
			}
		    },

		    onFailure: function(resp) {
			showsendfailed(function() {server.removefromquicklist(itemid);});
		    }
		});

	    request.send({ data: {
			action: 'removefromquicklist',
			    itemid: itemid,
			    id: this.id,
			    userid: this.userid, // This and pageno are not strictly needed, but are checked for on the server
			    pageno: this.pageno,
			    sesskey: this.sesskey
			    } });
	},

	getimageurl: function(pageno, changenow) {
	    if (!this.js_navigation) {
		return; // Only preload pages if using js navigation method
	    }
	    if (changenow) {
		if ($defined(pagelist[pageno])) {
		    showpage(pageno);
		    pagesremaining++;
		    if (pagesremaining > 1) {
			return; // Already requests pending, so no need to send any more
		    }
		} else {
		    waitingforpage = pageno;
		    pagesremaining = pagestopreload; // Wanted a page that wasn't preloaded, so load a few more
		    $('pdfimg').setProperty('src',server_config.blank_image);
		}
	    }
	    
	    var pagecount = server_config.pagecount.toInt();
	    if (pageno > pagecount) {
		pageno = 1;
	    }
	    var startpage = pageno;

	    // Find the next page that has not already been loaded
	    while ((pageno <= pagecount) && $defined(pagelist[pageno])) {
		pageno++;
	    }
	    // Wrap around to the beginning again
	    if (pageno > pagecount) {
		pageno = 1;
		while ($defined(pagelist[pageno])) {
		    if (pageno == startpage) {
			return; // All pages preloaded, so stop
		    }
		    pageno++;
		}
	    }
	    
	    var request = new Request.JSON({
		    url: this.url,

		    onSuccess: function(resp) {
			server.retrycount = 0;
			if (resp.error == 0) {
			    pagesremaining--;
			    pagelist[pageno] = new Object();
			    pagelist[pageno].url = resp.image.url;
			    pagelist[pageno].width = resp.image.width;
			    pagelist[pageno].height = resp.image.height;
			    pagelist[pageno].image = new Image(resp.image.width, resp.image.height);
			    pagelist[pageno].image.src = resp.image.url;
			    if (waitingforpage == pageno) {
				showpage(pageno);
				waitingforpage = -1;
			    }

			    if (pagesremaining > 0) {
				var nextpage = pageno.toInt()+1;
				server.getimageurl(nextpage, false);
			    }
			    
			} else {
			    if (confirm(server_config.lang_errormessage+resp.errmsg+'\n'+server_config.lang_okagain)) {
				server.getimageurl(pageno, false);
			    }
			}
		    },

		    onFailure: function(resp) {
			showsendfailed(function() {server.getimageurl(pageno, false);});
		    }
		});

	    request.send({ data: {
			action: 'getimageurl',
			    id: this.id,
			    userid: this.userid,
			    pageno: pageno,
			    sesskey: this.sesskey
			    } });
	},

	addannotation: function(details, annotation) {
	    var waitel = new Element('div');
	    waitel.set('class', 'pagewait');
	    $('pdfholder').adopt(waitel);

	    if (!$defined(details.id)) {
		details.id = -1;
	    }

	    var request = new Request.JSON({
		    url: this.url,

		    onSuccess: function(resp) {
			server.retrycount = 0;
			waitel.destroy();

			if (resp.error == 0) {
			    if (details.id < 0) { // A new line
				annotation.store('id', resp.id);
				if ($defined(lineselect) && (annotation.retrieve("paper") == lineselect.paper)) {
				    unselectline();
				    annotation.fireEvent('click');
				}
			    }
			} else {
			    if (confirm(server_config.lang_errormessage+resp.errmsg+'\n'+server_config.lang_okagain)) {
				server.addannotation(details, annotation);
			    }
			}
		    },

		    onFailure: function(resp) {
			waitel.destroy();
			showsendfailed(function() {server.addannotation(details, annotation);});
		    }
		    
		});

	    request.send({ data: {
			action: 'addannotation',
			    annotation_startx: details.coords.sx,
			    annotation_starty: details.coords.sy,
			    annotation_endx: details.coords.ex,
			    annotation_endy: details.coords.ey,
			    annotation_colour: details.colour,
			    annotation_type: details.type,
			    annotation_id: details.id,
			    id: this.id,
			    userid: this.userid,
			    pageno: this.pageno,
			    sesskey: this.sesskey
			    } });
	},

	removeannotation: function(aid) {
	    var request = new Request.JSON({
		    url: this.url,
		    onSuccess: function(resp) {
			server.retrycount = 0;
			if (resp.error != 0) {
			    if (confirm(server_config.lang_errormessage+resp.errmsg+'\n'+server_config.lang_okagain)) {
				server.removeannotation(aid);
			    }
			}
		    },
		    onFailure: function(resp) {
			showsendfailed(function() {server.removeannotation(aid);} );
		    }
		});

	    request.send({
		    data: {
  			    action: 'removeannotation',
			    annotationid: aid,
			    id: this.id,
			    userid: this.userid,
			    pageno: this.pageno,
			    sesskey: this.sesskey
			    }
		});
	}
	
    });

function showsendfailed(resend) {
    if (pageunloading) {
	return;
    }

    // If less than 2 failed messages since the last successful
    // message, then try again immediately
    if (server.resendfailed < 2) {
	server.resendfailed++;
	resend();
	return;
    }
    
    var el = $('sendagain');
    el.addEvent('click', resend);
    el.addEvent('click', hidesendfailed);
    $('sendfailed').setStyles({display: 'block', position: 'absolute', top: 200, left: 200, 'z-index': 9999, 'background-color': '#d0d0d0', 'border': 'black 1px solid', padding: 10});
}

function hidesendfailed() {
    $('sendagain').removeEvents();
    $('sendfailed').setStyle('display', 'none');
}

function setcommentcontent(el, content) {
    el.store('rawtext', content);

    // Replace special characters with html entities
    content = content.replace(/</gi,'&lt;');
    content = content.replace(/>/gi,'&gt;');
    content = content.replace(/\n/gi, '<br />');
    var resizehandle = el.retrieve('resizehandle');
    el.set('html',content);
    el.adopt(resizehandle);
}

function updatelastcomment() {
    // Stop trapping 'escape'
    document.removeEvent('keydown', typingcomment);

    var updated = false;
    var content = null;
    if ($defined(editbox)) {
	content = editbox.get('value');
	editbox.destroy();
	editbox = null;
    }
    if ($defined(currentcomment)) {
	if (!$defined(content) || (content.trim() == '')) {
	    id = currentcomment.retrieve('id');
	    if (id != -1) {
		server.removecomment(id);
	    }
	    currentcomment.destroy();

	} else {
	    oldcolour = currentcomment.retrieve('oldcolour');
	    newcolour = currentcomment.retrieve('colour');
	    if ((content == currentcomment.retrieve('rawtext')) && (newcolour == oldcolour)) {
		setcommentcontent(currentcomment, content);
		currentcomment.retrieve('drag').attach();
		// Do not update the server when the text is unchanged
	    } else {
		setcommentcontent(currentcomment, content);
		server.updatecomment(currentcomment);
	    }
	}
	currentcomment = null;
	updated = true;
    }

    return updated;
}

function makeeditbox(comment, content) {
    if (!$defined(content)) {
	content = '';
    }
    
    editbox = new Element('textarea');
    editbox.set('rows', '5');
    editbox.set('wrap', 'soft');
    editbox.set('value', content);
    comment.adopt(editbox);
    editbox.focus();

    document.addEvent('keydown', typingcomment);
    comment.retrieve('drag').detach(); // No dragging whilst editing (it messes up the text selection)
}

function makecommentbox(position, content, colour) {
    // Create the comment box
    newcomment = new Element('div');
    $('pdfholder').adopt(newcomment);

    if ($defined(colour)) {
	setcolourclass(colour, newcomment);
    } else {
	setcolourclass(getcurrentcolour(), newcomment);
    }
    newcomment.store('oldcolour',colour);
    //newcomment.set('class', 'comment');
    if (Browser.Engine.trident) {
	// Does not work with FF & Moodle
	newcomment.setStyles({ left: position.x, top: position.y });
    } else {
	// Does not work with IE
	newcomment.set('style', 'position:absolute; top:'+position.y+'px; left:'+position.x+'px;');
    }
    newcomment.store('id', -1);
    
    if (context_comment) {
	context_comment.addmenu(newcomment);
    }

    var drag = newcomment.makeDraggable({
	    container: 'pdfholder',
	    onCancel: editcomment, // Click without drag = edit
	    onStart: function(el) {
		if (resizing) {
		    el.retrieve('drag').stop();
		} else if (el.retrieve('id') == -1) {
		    el.retrieve('drag').stop();
		}
	    },
	    onComplete: function(el) { server.updatecomment(el); }
	});
    newcomment.store('drag', drag); // Remember the drag object so  we can switch it on later

    var resizehandle = new Element('div');
    resizehandle.set('class','resizehandle');
    newcomment.adopt(resizehandle);
    var resize = newcomment.makeResizable({
	    container: 'pdfholder',
	    handle: resizehandle,
	    modifiers: {'x': 'width', 'y': null},
	    onBeforeStart: function(el) { resizing = true; },
	    onStart: function(el) {
		// Do not allow resizes on comments that have not yet
		// got an id from the server (except when still editing
		// the text, as that is OK)
		if (!$defined(editbox)) {
		    if (el.retrieve('id') == -1) {
			el.retrieve('resize').stop();
		    }
		}
	    },
	    onComplete: function(el) {
		resizing = false;
		if (!$defined(editbox)) { 
		    server.updatecomment(el); // Do not update on resize when editing the text
		}
	    }
	});
    newcomment.store('resize', resize);
    newcomment.store('resizehandle', resizehandle);

    // Add the edit box to it
    if ($defined(content)) {
	setcommentcontent(newcomment, content);
    } else {
	makeeditbox(newcomment);
    }

    return newcomment;
}

function addcomment(e) {
    if (updatelastcomment()) {
	return;
    }

    if (currentpaper) { // In the middle of drawing a line
	return;
    }

    if (!e.control) {  // If control pressed, then drawing line
	// Calculate the relative position of the comment
	imgpos = $('pdfimg').getPosition();
	var offs = new Object();
	offs.x = e.page.x - imgpos.x;
	offs.y = e.page.y - imgpos.y;

	currentcomment = makecommentbox(offs);
    }
}

function editcomment(el) {
    unselectline();
    
    if (currentcomment == el) {
	return;
    }
    updatelastcomment();

    currentcomment = el;
    var resizehandle = currentcomment.retrieve('resizehandle');
    currentcomment.set('html','');
    currentcomment.adopt(resizehandle);
    var content = currentcomment.retrieve('rawtext');
    makeeditbox(currentcomment, content);
    setcurrentcolour(currentcomment.retrieve('colour'));
}

function typingcomment(e) {
    if (e.key == 'esc') {
	updatelastcomment();
	e.stop();
    }
}

function getcurrentcolour() {
    var el = $('choosecolour');
    var idx = el.selectedIndex;
    return el[idx].value;
}

function setcurrentcolour(colour) {
    var el = $('choosecolour');
    var i;
    for (i=0; i<el.length; i++) {
	if (el[i].value == colour) {
	    el.selectedIndex = i;
	    return;
	}
    }
}

function updatecommentcolour(colour, comment) {
    if (colour != comment.retrieve('colour')) {
	setcolourclass(colour, comment);
	setcurrentcolour(colour);
	if (comment != currentcomment) {
	    server.updatecomment(comment);
	}
    }
}

function setcolourclass(colour, comment) {
    if (comment) {
	if (colour == 'red') {
	    comment.set('class','comment commentred');
	} else if (colour == 'green') {
	    comment.set('class','comment commentgreen');
	} else if (colour == 'blue') {
	    comment.set('class','comment commentblue');
	} else if (colour == 'white') {
	    comment.set('class','comment commentwhite');
	} else if (colour == 'clear') {
	    comment.set('class','comment commentclear');
	} else {
	    // Default: yellow comment box
	    comment.set('class','comment commentyellow');
	    colour = 'yellow';
	}
	comment.store('colour', colour);
    }
}

function changecolour(e) {
    if (currentcomment) {
	var col = getcurrentcolour();
	if (col != currentcomment.retrieve('colour')) {
	    setcolourclass(getcurrentcolour(), currentcomment);
	}
    }
    Cookie.write('uploadpdf_colour', getcurrentcolour());
}

function getcurrentlinecolour() {
    var el = $('chooselinecolour');
    var idx = el.selectedIndex;
    return el[idx].value;
}

function setcurrentlinecolour(colour) {
    var el = $('chooselinecolour');
    var i;
    for (i=0; i<el.length; i++) {
	if (el[i].value == colour) {
	    el.selectedIndex = i;
	    return;
	}
    }
}

function setlinecolour(colour, line) {
    if (line) {
	var rgb;
	if (colour == "yellow") { rgb = "#ff0"; }
	else if (colour == "green") { rgb = "#0f0"; }
	else if (colour == "blue") { rgb = "#00f"; }
	else if (colour == "white") { rgb = "#fff"; }
	else if (colour == "black") { rgb = "#000"; }
	else { rgb = "#f00"; } // Red
	line.attr("stroke", rgb);
    }
}

function changelinecolour(e) {
    if ($defined(lineselect)) {
	var canvas = $(lineselect.paper.canvas);
	if (!lineselectid) {
	    // Cannot change the colour before the server has responded
	    setcurrentlinecolour(canvas.retrieve("colour"));
	} else {
	    var canvas = $(lineselect.paper.canvas);
	    var line = canvas.retrieve("line");
	    var colour = getcurrentlinecolour();
	    if (canvas.retrieve("colour") != colour) {
		setlinecolour(colour, line);
		canvas.store("colour", colour);
		server.addannotation({type: "line", colour: colour, id: canvas.retrieve("id"), coords: {sx:-1,sy:-1,ex:-1,ey:-1} }, canvas);
	    }
	}
    }
    Cookie.write('uploadpdf_linecolour', getcurrentlinecolour());
}

function startline(e) {
    unselectline();

    if (currentpaper) {
	return true; // If user clicks very quickly this can happen
    }

    if (!e.control) {
	return true;
    }

    e.preventDefault(); // Stop FF from dragging the image

    var dims = $('pdfimg').getCoordinates();
    var sx = e.page.x - dims.left;
    var sy = e.page.y - dims.top;
    
    currentpaper = Raphael(dims.left,dims.top,dims.width,dims.height);
    $(document).addEvent('mousemove', updateline);
    linestartpos = {x: sx, y: sy};

    return false;
}

function updateline(e) {
    var dims = $('pdfimg').getCoordinates();
    var ex = e.page.x - dims.left;
    var ey = e.page.y - dims.top;

    if ($defined(currentline)) {
	currentline.remove();
    } else {
	// Doing this earlier catches the starting mouse click by mistake
	$(document).addEvent('mouseup',finishline);
    }
    currentline = currentpaper.path("M "+linestartpos.x+" "+linestartpos.y+" L"+ex+" "+ey);
    currentline.attr("stroke-width", 3);
    setlinecolour(getcurrentlinecolour(), currentline);
}

function finishline(e) {
    $(document).removeEvent('mousemove', updateline);
    $(document).removeEvent('mouseup', finishline);
    
    if (!$defined(currentpaper)) {
	return;
    }

    var dims = $('pdfimg').getCoordinates();
    var coords = {sx:linestartpos.x, sy:linestartpos.y, ex: (e.page.x-dims.left), ey: (e.page.y-dims.top)};

    currentpaper.remove();
    currentpaper = null;
    currentline = null;

    makeline(coords);
}

function makeline(coords, id, colour) {
    var linewidth = 3.0;
    var halflinewidth = linewidth * 0.5;
    var dims = $('pdfimg').getCoordinates();
    var startcoords = { sx: coords.sx, sy: coords.sy, ex: coords.ex, ey: coords.ey };

    if (!$defined(colour)) {
	colour = getcurrentlinecolour();
    }
    var details = {type: "line", coords: startcoords, colour: colour};

    coords.sx += dims.left;   coords.ex += dims.left;
    coords.sy += dims.top;    coords.ey += dims.top;

    if (coords.sx > coords.ex) { // Always go left->right
	var temp = coords.sx; coords.sx = coords.ex; coords.ex = temp;
	temp = coords.sy;     coords.sy = coords.ey; coords.ey = temp;
    }
    if (coords.sy < coords.ey) {
	var boundary = {x: (coords.sx-halflinewidth), y: (coords.sy-halflinewidth), w: (coords.ex+linewidth-coords.sx), h: (coords.ey+linewidth-coords.sy)};
	coords.sy = halflinewidth; coords.ey = boundary.h - halflinewidth;
    } else {
	var boundary = {x: (coords.sx-halflinewidth), y: (coords.ey-halflinewidth), w: (coords.ex+linewidth-coords.sx), h: (coords.sy+linewidth-coords.ey)};
	coords.sy = boundary.h - halflinewidth; coords.ey = halflinewidth;
    }
    coords.sx = halflinewidth; coords.ex = boundary.w - halflinewidth;
    var paper = Raphael(boundary.x, boundary.y, boundary.w+2, boundary.h+2);
    var line = paper.path("M "+coords.sx+" "+coords.sy+" L "+coords.ex+" "+coords.ey);
    line.attr("stroke-width", linewidth);
    setlinecolour(colour, line);
    
    var domcanvas = $(paper.canvas);

    domcanvas.store('paper',paper);
    domcanvas.store('width',boundary.w);
    domcanvas.store('height',boundary.h);
    domcanvas.store('line',line);
    domcanvas.store('colour',colour);
    domcanvas.addEvent('mousedown',startline);
    domcanvas.addEvent('click', selectline);
    if ($defined(id)) {
	domcanvas.store('id',id);
    } else {
	server.addannotation(details, domcanvas);
    }

    allannotations.push(paper);
}

function selectline(e) {
    var paper = this.retrieve('paper');
    var width = this.retrieve('width');
    var height = this.retrieve('height');
    lineselectid = this.retrieve('id');
    if (!lineselectid) {
	colour = "#f44";
    } else {
	colour = "#555";
    }
    lineselect = paper.rect(1,1,width-2,height-2).attr({stroke: colour, "stroke-dasharray": "- ", "stroke-width": 1, fill: null});
    
    updatelastcomment(); // In case we were editing a comment at the time
    document.addEvent('keydown', checkdeleteline);
    var linecolour = this.retrieve('colour');
    setcurrentlinecolour(linecolour);
}

function unselectline() {
    if ($defined(lineselect)) {
	lineselect.remove();
	lineselect = null;
	lineselectid = null;
	document.removeEvent('keydown', checkdeleteline);
    }
}

function checkdeleteline(e) {
    if (e.key == 'delete') {
	if ($defined(lineselect)) {
	    if (lineselectid) {
		var paper = lineselect.paper;
		allannotations.erase(paper);
		paper.remove();
		lineselect = null;
		document.removeEvent('keydown', checkdeleteline);
		server.removeannotation(lineselectid);
		lineselectid = null;
	    }
	}
    }
}

function keyboardnavigation(e) {
    if ($defined(currentcomment)) {
	return; // No keyboard navigation when editing comments
    }

    if (e.key == 'n') {
	gotonextpage();
    } else if (e.key == 'p') {
	gotoprevpage();
    }
}

function startjs() {
    new Asset.css('style/annotate.css');
    server = new ServerComm(server_config);
    server.getcomments();

    $('pdfimg').addEvent('click', addcomment);
    $('pdfimg').addEvent('mousedown', startline);
    $('pdfimg').ondragstart = function() { return false; }; // To stop ie trying to drag the image
    var colour = Cookie.read('uploadpdf_colour');
    if ($defined(colour)) {
	setcurrentcolour(colour);
    }
    var linecolour = Cookie.read('uploadpdf_linecolour');
    if ($defined(linecolour)) {
	setcurrentlinecolour(linecolour);
    }
    $('choosecolour').addEvent('change', changecolour);
    $('chooselinecolour').addEvent('change', changelinecolour);

    // Start preloading pages if using js navigation method
    if (server_config.js_navigation) {
        document.addEvent('keydown', keyboardnavigation);
	pagelist = new Array();
	var pageno = server.pageno.toInt();
	// Little fix as Firefox remembers the selected option after a page refresh
	var sel = $('selectpage');
	var selidx = sel.selectedIndex;
	var selpage = sel[selidx].value;
	if (selpage != pageno) {
	    gotopage(selpage);
	} else {
	    server.getimageurl(pageno+1, false);
	}
    }

    window.addEvent('beforeunload', function() {
	    pageunloading = true;
	});
}

function context_quicklistnoitems() {
    if (context_quicklist.quickcount == 0) {
	if (!context_quicklist.menu.getElement('a[href$=noitems]')) {
	    context_quicklist.addItem('noitems', server_config.lang_emptyquicklist+' &#0133;', null, function() { alert(server_config.lang_emptyquicklist_instructions); });
	}
    } else {
	context_quicklist.removeItem('noitems');
    }
}

function addtoquicklist(item) {
    var itemid = item.id;
    var itemtext = item.text.trim().replace('\n','');
    var itemfulltext = false;
    if (itemtext.length > 30) {
	itemtext = itemtext.substring(0, 30) + '&#0133;';
	itemfulltext = item.text.trim().replace('<','&lt;').replace('>','&gt;');
    }
    itemtext = itemtext.replace('<','&lt;').replace('>','&gt;');


    quicklist[itemid] = item;

    context_quicklist.addItem(itemid, itemtext, server_config.deleteicon, function(id, menu) {
	    var imgpos = $('pdfimg').getPosition();
	    var pos = new Object();
	    pos.x = menu.menu.getStyle('left').toInt() - imgpos.x;
	    pos.y = menu.menu.getStyle('top').toInt() - imgpos.y + 20;
	    // Nasty hack to reposition the comment box in IE
	    if (Browser.Engine.trident) {
		if (Browser.Engine.version <= 5) {
		    pos.x += 40;
		    pos.y -= 20;
		} else {
		    pos.y -= 15;
		}
	    }
	    var cb = makecommentbox(pos, quicklist[id].text, quicklist[id].colour);
	    if (Browser.Engine.trident) {
		// Does not work with FF & Moodle
		cb.setStyle('width',quicklist[id].width);
	    } else {
		// Does not work with IE
		var style = cb.get('style')+' width:'+quicklist[id].width+'px;';
		cb.set('style',style);
	    }
	    server.updatecomment(cb);
	}, itemfulltext );

    context_quicklist.quickcount++;
    context_quicklistnoitems();
}

function removefromquicklist(itemid) {
    context_quicklist.removeItem(itemid);
    context_quicklist.quickcount--;
    context_quicklistnoitems();
}

function initcontextmenu() {
    //create a context menu
    context_quicklist = new ContextMenu({
	    targets: null,
	    menu: 'context-quicklist',
	    actions: {
		removeitem: function(itemid, menu) {
		    server.removefromquicklist(itemid);
		}
	    }
	});
    context_quicklist.addmenu($('pdfimg'));
    context_quicklist.quickcount = 0;
    context_quicklistnoitems();
    quicklist = new Array();

    if (Browser.Engine.trident && Browser.Engine.version <= 5) {
	// Hack to draw the separator line correctly in IE7 and below
	var menu = document.getElementById('context-comment');
	var items = menu.getElementsByTagName('li');
	var n;
	for (n in items) {
	    if (items[n].className == 'separator') {
		items[n].className = 'separatorie7';
	    }
	}
    }

    context_comment = new ContextMenu({
	    targets: null,
	    menu: 'context-comment',
	    actions: {
		addtoquicklist: function(element,ref) {
		    server.addtoquicklist(element);
		},
		red: function(element,ref) { updatecommentcolour('red',element); },
		yellow: function(element,ref) { updatecommentcolour('yellow',element); },
		green: function(element,ref) { updatecommentcolour('green',element); },
		blue: function(element,ref) { updatecommentcolour('blue',element); },
		white: function(element,ref) { updatecommentcolour('white',element); },
		clear: function(element,ref) { updatecommentcolour('clear',element); },
		deletecomment: function(element, ref) {
		    var id = element.retrieve('id');
		    if (id != -1) {
			server.removecomment(id);
		    }
		    element.destroy();
		}
	    }
	});

    server.getquicklist();
}

function showpage(pageno) {
    var pdfsize = $('pdfsize');
    if (Browser.Engine.trident) {
	// Does not work with FF & Moodle
	pdfsize.setStyle('width',pagelist[pageno].width);
	pdfsize.setStyle('height',pagelist[pageno].height);
    } else {
	// Does not work with IE
	var style = 'height:'+pagelist[pageno].height+'px; width:'+pagelist[pageno].width+'px;'+' clear: both;';
	pdfsize.set('style',style);
    }
    var pdfimg = $('pdfimg');
    pdfimg.setProperty('width',pagelist[pageno].width);
    pdfimg.setProperty('height',pagelist[pageno].height);
    if (pagelist[pageno].image.complete) {
	pdfimg.setProperty('src',pagelist[pageno].url);
    } else {
	pdfimg.setProperty('src',server_config.blank_image);
	setTimeout('check_pageimage('+pageno+')', 200);
    }
    server.getcomments();
}

function check_pageimage(pageno) {
    if (pageno != server.pageno.toInt()) {
	return; // Moved off the page in question
    }
    if (pagelist[pageno].image.complete) {
	$('pdfimg').setProperty('src',pagelist[pageno].url);
    } else {
	setTimeout('check_pageimage('+pageno+')', 200);
    }
}

function gotopage(pageno) {
    var pagecount = server_config.pagecount.toInt();
    if ((pageno <= pagecount) && (pageno > 0)) {
	$('pdfholder').getElements('div').destroy(); // Destroy all the currently displayed comments
	allannotations.each(function(p) { p.remove(); });
	allannotations.empty();
	currentpaper = currentline = lineselect = null;
	currentcomment = null; // Throw away any comments in progress
	editbox = null;

	// Set the dropdown selects to have the correct page number in them
	var el = $('selectpage');
	var i;
	for (i=0; i<el.length; i++) {
	    if (el[i].value == pageno) {
		el.selectedIndex = i;
		break;
	    }
	}
	el = $('selectpage2');
	for (i=0; i<el.length; i++) {
	    if (el[i].value == pageno) {
		el.selectedIndex = i;
		break;
	    }
	}

	// Update the 'showprevious' form
	if ($defined($('showprevious'))) {
	    document.showprevious.pageno.value = pageno;
	}
	// Update the 'open in new window' link
	var opennew = $('opennewwindow');
	var on_link = opennew.get('href').replace(/pageno=\d+/,"pageno="+pageno);
	opennew.set('href', on_link);
    
	//Update the next/previous buttons
	if (pageno == pagecount) {
	    $('nextpage').set('disabled', 'disabled');
	    $('nextpage2').set('disabled', 'disabled');
	} else {
	    $('nextpage').erase('disabled');
	    $('nextpage2').erase('disabled');
	}
	if (pageno == 1) {
	    $('prevpage').set('disabled', 'disabled');
	    $('prevpage2').set('disabled', 'disabled');
	} else {
	    $('prevpage').erase('disabled');
	    $('prevpage2').erase('disabled');
	}
	
	server.pageno = ""+pageno;
	server.getimageurl(pageno, true);
    }
}

function gotonextpage() {
    var pageno = server.pageno.toInt();
    pageno += 1;
    gotopage(pageno);
}

function gotoprevpage() {
    var pageno = server.pageno.toInt();
    pageno -= 1;
    gotopage(pageno);
}

function selectpage() {
    var el = $('selectpage');
    var idx = el.selectedIndex;
    gotopage(el[idx].value);
}

function selectpage2() {
    var el = $('selectpage2');
    var idx = el.selectedIndex;
    gotopage(el[idx].value);
}

window.addEvent('domready', function() {
	startjs();
	initcontextmenu();
    });
