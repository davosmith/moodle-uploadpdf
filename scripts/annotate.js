var currentcomment = null;	// The comment that is currently being edited
var editbox = null;		// The edit box that is currently displayed
//var editwidth = 20;
var resizing = false;		// A box is being resized (so disable dragging)
var server = null;		// The object use to send data back to the server

var ServerComm = new Class({
	Implements: [Events],
	id: null,
	uid: null,
	pageno: null,

	initialize: function(id, uid, pageno) {
	    this.id = id;
	    this.uid = uid;
	    this.pageno = pageno;
	},

	updatecomment: function(comment) {
	    var waitel = new Element('div');
	    waitel.set('class', 'wait');
	    comment.adopt(waitel);
	    var request = new Request.JSON({
		    url: 'updatecomment.php',
		    
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
			comment: {
			    position: { x: comment.getStyle('left'), y: comment.getStyle('top') },
			    width: comment.getStyle('width'),
			    text: comment.retrieve('rawtext'),
			    id: comment.retrieve('id')
			    },
			id: this.id,
			uid: this.uid,
			pageno: this.pageno
			    }
		});
	},

	removecomment: function(cid) {
	    var request = new Request.JSON({
		    url: 'updatecomment.php',
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
			    uid: this.uid,
			    pageno: this.pageno
			    }
		});
	},

	getcomments: function() {
	    var waitel = new Element('div');
	    waitel.set('class', 'pagewait');
	    $('pdfholder').adopt(waitel);
	    
	    var request = new Request.JSON({
		    url: 'updatecomment.php',

		    onSuccess: function(resp) {
			if (resp.error == 0) {
			    waitel.destroy();
			    resp.comments.each(function(comment) {
				    cb = makecommentbox(comment.position, comment.text);
				    cb.setStyle('width', comment.width);
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
			    uid: this.uid,
			    pageno: this.pageno
			    } });
	}
    });

    // Wordwrap the text
    /*
      var pos=0;
      var count=0;
      var lastspace = -1;

      while (pos < content.length) {
      if (lastspace >= 0) {
      content = content.slice(0, lastspace)+'\n'+content.slice(lastspace+1);
      count = pos - lastspace;
      } 
      lastspace = -1;
      do {
      if (content.charAt(pos) == ' ') {
      lastspace = pos;
      } else if (content.charAt(pos) == '\n') {
      count = 0;
      lastspace = -1;
      } 
      count++;
      pos++;
      } while ((count < editwidth) && (pos < content.length)) 
      }*/

function setcommentcontent(el, content) {
    el.store('rawtext', content);

    // Replace special characters with html entities
    content = content.replace(/</gi,'&lt;');
    content = content.replace(/>/gi,'&gt;');
    content = content.replace(/\n/gi, '<br>');
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
    newcomment.setStyles({ left: position.x, top: position.y });
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
	server.getcomments();
	$('pdfimg').addEvent('click', addcomment);
    });
