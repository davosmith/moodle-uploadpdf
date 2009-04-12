var currentcomment = null;	// The comment that is currently being edited
var editbox = null;		// The edit box that is currently displayed
//var editwidth = 20;
var resizing = false;		// A box is being resized (so disable dragging)
var server = null;		// The object use to send data back to the server

var ServerComm = new Class({
	Implements: [Events],
	id: null,
	userid: null,
	pageno: null,
	sesskey: null,
	url: null,

	initialize: function(settings) {
	    this.id = settings.id;
	    this.userid = settings.userid;
	    this.pageno = settings.pageno;
	    this.sesskey = settings.sesskey;
	    this.url = settings.updatepage;
	},

	updatecomment: function(comment) {
	    var waitel = new Element('div');
	    waitel.set('class', 'wait');
	    comment.adopt(waitel);
	    var request = new Request.JSON({
		    url: this.url,
		    
		    onSuccess: function(resp) {
			waitel.destroy();

			if (resp.error == 0) {
			    comment.store('id', resp.id);
			    //setcommentcontent(comment, resp.text);
			    // Re-attach drag and resize ability
			    comment.retrieve('drag').attach();
			} else {
			    if (confirm('Error message: '+resp.errmsg+'\nOK to try again')) {
				server.updatecomment(comment);
			    } else {
				// Re-attach drag and resize ability
				comment.retrieve('drag').attach();
			    }
			}
		    },
		    
		    onFailure: function(req) {
			waitel.destroy();
			if (confirm('Server communication failed - OK to try again')) {
			    server.updatecomment(comment);
			} else {
			    comment.retrieve('drag').attach();
			}
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
			if (resp.error != 0) {
			    if (confirm('Error message: '+resp.errmsg+'\nOK to try again')) {
				server.removecomment(cid);
			    }
			}
		    },
		    onFailure: function(resp) {
			if (confirm('Server communication failed - OK to try again')) {
			    server.removecomment(cid);
			}
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
	    
	    var request = new Request.JSON({
		    url: this.url,

		    onSuccess: function(resp) {
			if (resp.error == 0) {
			    waitel.destroy();
			    resp.comments.each(function(comment) {
				    cb = makecommentbox(comment.position, comment.text);
				    var style = cb.get('style')+' width:'+comment.width+'px;';
				    cb.set('style',style);
				    //cb.setStyle('width',comment.width); // This should work, but doesn't
				    cb.store('id', comment.id);
				});
			} else {
			    if (confirm('Error from server - '+resp.errmsg+'\nOK to try again')) {
				server.getcomments();
			    } else {
				waitel.destroy();
			    }
			}
		    },

		    onFailure: function(resp) {
			if (confirm('Server communication failed - OK to try again')) {
			    server.getcomments();
			} else {
			    waitel.destroy();
			}
		    },
		});

	    request.send({ data: {
			    action: 'getcomments',
			    id: this.id,
			    userid: this.userid,
			    pageno: this.pageno,
			    sesskey: this.sesskey
			    } });
	}
    });

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
    window.removeEvent('keydown', typingcomment);

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
	    if (content == currentcomment.retrieve('rawtext')) {
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

    window.addEvent('keydown', typingcomment);
    comment.retrieve('drag').detach(); // No dragging whilst editing (it messes up the text selection)
}

function makecommentbox(position, content) {
    // Create the comment box
    newcomment = new Element('div');
    newcomment.set('class', 'comment');
    //    newcomment.setStyles({ left: position.x, top: position.y }); // This should work, but doesn't
    newcomment.set('style', 'position:absolute; left:'+position.x+'px; top:'+position.y+'px;');
    newcomment.store('id', -1);
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

    $('pdfholder').adopt(newcomment);
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
   
    // Calculate the relative position of the comment
    imgpos = $('pdfimg').getPosition();
    var offs = new Object();
    offs.x = e.page.x - imgpos.x;
    offs.y = e.page.y - imgpos.y;

    currentcomment = makecommentbox(offs);
}

function editcomment(el) {
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
}

function typingcomment(e) {
    if (e.key == 'esc') {
	updatelastcomment();
	e.stop();
    }
}

window.addEvent('domready', function() {
	new Asset.css('style/annotate.css');
	server = new ServerComm(server_config);
	server.getcomments();
	$('pdfimg').addEvent('click', addcomment);
    });
