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
var lasthighlight = null;

var colourmenu = null;
var linecolourmenu = null;
var nextbutton = null;
var prevbutton = null;
var choosedrawingtool = null;
var findcommentsmenu = null;
var stampmenu = null;

var resendtimeout = 4000;

// All to do with line drawing
var currentpaper = null;
var currentline = null;
var linestartpos = null;
var freehandpoints = null;
//var lineselect = null;
//var lineselectid = null;
var allannotations = new Array();

var LINEWIDTH = 3.0;
var HIGHLIGHT_LINEWIDTH = 14.0;

var $defined = function(obj) { return (obj != undefined); };

var ServerComm = new Class({
    Implements: [Events],
    id: null,
    userid: null,
    pageno: null,
    sesskey: null,
    url: null,
    js_navigation: true,
    retrycount: 0,
    editing: true,
    waitel: null,
    pageloadcount: 0,  // Store how many page loads there have been
    // (to allow ignoring of messages from server that arrive after page has changed)
    scrolltocommentid: 0,

    initialize: function(settings) {
        this.id = settings.id;
        this.userid = settings.userid;
        this.pageno = settings.pageno;
        this.sesskey = settings.sesskey;
        this.url = settings.updatepage;
        this.js_navigation = settings.js_navigation;
        this.editing = settings.editing.toInt();

        this.waitel = new Element('div');
        this.waitel.set('class', 'pagewait hidden');
        document.id('pdfholder').adopt(this.waitel);
    },

    updatecomment: function(comment) {
        if (!this.editing) {
            return;
        }
        var waitel = new Element('div');
        waitel.set('class', 'wait');
        comment.adopt(waitel);
        comment.store('oldcolour', comment.retrieve('colour'));
        var pageloadcount = this.pageloadcount;
        var request = new Request.JSON({
            url: this.url,
            timeout: resendtimeout,

            onSuccess: function(resp) {
                if (pageloadcount != server.pageloadcount) { return; }
                server.retrycount = 0;
                if (typeof waitel.destroy != 'undefined') { waitel.destroy(); }

                if (resp.error == 0) {
                    comment.store('id', resp.id);
                    // Re-attach drag and resize ability
                    comment.retrieve('drag').attach();
                    updatefindcomments(server.pageno.toInt(), resp.id, comment.retrieve('rawtext'));
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
                if (pageloadcount != server.pageloadcount) { return; }
                if (typeof waitel.destroy != 'undefined') { waitel.destroy(); }
                showsendfailed(function() {server.updatecomment(comment);});
                // TODO The following should really be on the 'cancel' (but probably unimportant)
                comment.retrieve('drag').attach();
            },

            onTimeout: function() {
                if (pageloadcount != server.pageloadcount) { return; }
                if (typeof waitel.destroy != 'undefined') { waitel.destroy(); }
                showsendfailed(function() {server.updatecomment(comment);});
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
        if (!this.editing) {
            return;
        }
        removefromfindcomments(cid);
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
        this.waitel.removeClass('hidden');
        var pageno = this.pageno;
        var scrolltocommentid = this.scrolltocommentid;
        this.scrolltocommentid = 0;

        var request = new Request.JSON({
            url: this.url,

            onSuccess: function(resp) {
                server.retrycount = 0;
                server.waitel.addClass('hidden');
                if (resp.error == 0) {
                    if (pageno == server.pageno) { // Make sure the page hasn't changed since we sent this request
                        //document.id('pdfholder').getElements('div').destroy(); // Destroy all the currently displayed comments (just in case!) - this turned out to be a bad idea
                        resp.comments.each(function(comment) {
                            cb = makecommentbox(comment.position, comment.text, comment.colour);
                            if (Browser.ie && Browser.version < 9) {
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
                        allannotations.each(function(p) {p.destroy()});
                        allannotations.empty();
                        resp.annotations.each(function(annotation) {
                            var coords;
                            if (annotation.type == 'freehand') {
                                coords = new Array();
                                points = annotation.path.split(',');
                                for (var i=0; (i+1)<points.length; i+=2) {
                                    coords.push({x:points[i].toInt(), y:points[i+1].toInt()});
                                }
                            } else {
                                coords = {
                                    sx: annotation.coords.startx.toInt(),
                                    sy: annotation.coords.starty.toInt(),
                                    ex: annotation.coords.endx.toInt(),
                                    ey: annotation.coords.endy.toInt()
                                };
                            }
                            makeline(coords, annotation.type, annotation.id, annotation.colour, annotation.path);
                        });

                        doscrolltocomment(scrolltocommentid);
                    }
                } else {
                    if (confirm(server_config.lang_errormessage+resp.errmsg+'\n'+server_config.lang_okagain)) {
                        server.getcomments();
                    }
                }
            },

            onFailure: function(resp) {
                showsendfailed(function() {server.getcomments();});
                server.waitel.addClass('hidden');
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
        if (!this.editing) {
            return;
        }
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
        if (!this.editing) {
            return;
        }
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
        if (!this.editing) {
            return;
        }
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
                document.id('pdfimg').setProperty('src',server_config.blank_image);
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
        if (!this.editing) {
            return;
        }
        this.waitel.removeClass('hidden');

        if (!$defined(details.id)) {
            details.id = -1;
        }

        var pageloadcount = this.pageloadcount;

        var request = new Request.JSON({
            url: this.url,

            onSuccess: function(resp) {
                if (pageloadcount != server.pageloadcount) { return; }
                server.retrycount = 0;
                server.waitel.addClass('hidden');

                if (resp.error == 0) {
                    if (details.id < 0) { // A new line
                        annotation.store('id', resp.id);
                        //if ($defined(lineselect) && (annotation.retrieve("paper") == lineselect.paper)) {
                        //  unselectline();
                        //  annotation.fireEvent('click');
                        //}
                    }
                } else {
                    if (confirm(server_config.lang_errormessage+resp.errmsg+'\n'+server_config.lang_okagain)) {
                        server.addannotation(details, annotation);
                    }
                }
            },

            onFailure: function(resp) {
                if (pageloadcount != server.pageloadcount) { return; }
                server.waitel.addClass('hidden');
                showsendfailed(function() {server.addannotation(details, annotation);});
            }

        });

        var requestdata = {
            action: 'addannotation',
            annotation_colour: details.colour,
            annotation_type: details.type,
            annotation_id: details.id,
            id: this.id,
            userid: this.userid,
            pageno: this.pageno,
            sesskey: this.sesskey
        };

        if (details.type == 'freehand') {
            requestdata.annotation_path = details.path;
        } else {
            requestdata.annotation_startx = details.coords.sx;
            requestdata.annotation_starty = details.coords.sy;
            requestdata.annotation_endx = details.coords.ex;
            requestdata.annotation_endy = details.coords.ey;
        }
        if (details.type == 'stamp') {
            requestdata.annotation_path = details.path;
        }
        request.send({ data: requestdata }); // Move this line down, once all working
    },

    removeannotation: function(aid) {
        if (!this.editing) {
            return;
        }
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
    },

    scrolltocomment: function(commentid) {
        this.scrolltocommentid = commentid;
    }

});

function showsendfailed(resend) {
    if (pageunloading) {
        return;
    }

    // If less than 2 failed messages since the last successful
    // message, then try again immediately
    if (server.retrycount < 2) {
        server.retrycount++;
        resend();
        return;
    }

    var el = document.id('sendagain');
    el.addEvent('click', resend);
    el.addEvent('click', hidesendfailed);
    document.id('sendfailed').setStyles({display: 'block', position: 'absolute', top: 200, left: 200, 'z-index': 9999, 'background-color': '#d0d0d0', 'border': 'black 1px solid', padding: 10});
}

function hidesendfailed() {
    document.id('sendagain').removeEvents();
    document.id('sendfailed').setStyle('display', 'none');
}

function setcommentcontent(el, content) {
    el.store('rawtext', content);

    // Replace special characters with html entities
    content = content.replace(/</gi,'&lt;');
    content = content.replace(/>/gi,'&gt;');
    if (Browser.ie7) { // Grrr... no 'pre-wrap'
        content = content.replace(/\n/gi,'<br/>');
        content = content.replace(/  /gi,' &nbsp;');
    }
    var resizehandle = el.retrieve('resizehandle');
    el.set('html',content);
    el.adopt(resizehandle);
}

function updatelastcomment() {
    if (!server.editing) {
        return;
    }
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
    if (!server.editing) {
        return;
    }

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
    document.id('pdfholder').adopt(newcomment);

    if ($defined(colour)) {
        setcolourclass(colour, newcomment);
    } else {
        setcolourclass(getcurrentcolour(), newcomment);
    }
    newcomment.store('oldcolour',colour);
    //newcomment.set('class', 'comment');
    if (Browser.ie && Browser.version < 9) {
        // Does not work with FF & Moodle
        newcomment.setStyles({ left: position.x, top: position.y });
    } else {
        // Does not work with IE
        newcomment.set('style', 'position:absolute; top:'+position.y+'px; left:'+position.x+'px;');
    }
    newcomment.store('id', -1);

    if (server.editing) {
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
    } else {  // !server.editing
        if ($defined(content)) { // Really should always be the case
            setcommentcontent(newcomment, content);
        }
    }

    return newcomment;
}

function addcomment(e) {
    if (!server.editing) {
        return;
    }
    if (updatelastcomment()) {
        return;
    }

    if (currentpaper) { // In the middle of drawing a line
        return;
    }

    if (getcurrenttool() != 'comment') {
        return;
    }

    var modifier = Browser.Platform.mac ? e.alt : e.control;
    if (!modifier) {  // If control pressed, then drawing line
        // Calculate the relative position of the comment
        imgpos = document.id('pdfimg').getPosition();
        var offs = new Object();
        offs.x = e.page.x - imgpos.x;
        offs.y = e.page.y - imgpos.y;

        currentcomment = makecommentbox(offs);
    }
}

function editcomment(el) {
    if (!server.editing) {
        return;
    }
    //unselectline();

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
    if (!server.editing) {
        return;
    }
    if (e.key == 'esc') {
        updatelastcomment();
        e.stop();
    }
}

function getcurrentcolour() {
    if (!server.editing) {
        return 'yellow';
    }
    return colourMenu.get("value");
}

function setcurrentcolour(colour) {
    if (!server.editing) {
        return;
    }
    if (colour != 'red' && colour != 'green' && colour != 'blue' && colour != 'white' && colour != 'clear') {
        colour = 'yellow';
    }
    colourMenu.set("label", '<img src="'+server_config.image_path+colour+'.gif" />');
    colourMenu.set("value", colour);
    changecolour();
}

function nextcommentcolour() {
    switch (getcurrentcolour()) {
    case 'red': setcurrentcolour('yellow'); break;
    case 'yellow': setcurrentcolour('green'); break;
    case 'green': setcurrentcolour('blue'); break;
    case 'blue': setcurrentcolour('white'); break;
    case 'white': setcurrentcolour('clear'); break;
    }
}

function prevcommentcolour() {
    switch (getcurrentcolour()) {
    case 'yellow': setcurrentcolour('red'); break;
    case 'green': setcurrentcolour('yellow'); break;
    case 'blue': setcurrentcolour('green'); break;
    case 'white': setcurrentcolour('blue'); break;
    case 'clear': setcurrentcolour('white'); break;
    }
}

function updatecommentcolour(colour, comment) {
    if (!server.editing) {
        return;
    }
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
    if (!server.editing) {
        return;
    }
    if (currentcomment) {
        var col = getcurrentcolour();
        if (col != currentcomment.retrieve('colour')) {
            setcolourclass(getcurrentcolour(), currentcomment);
        }
    }
    Cookie.write('uploadpdf_colour', getcurrentcolour());
}

function getcurrentlinecolour() {
    if (!server.editing) {
        return 'red';
    }
    return linecolourmenu.get("value");
}

function setcurrentlinecolour(colour) {
    if (!server.editing) {
        return;
    }
    if (colour != 'yellow' && colour != 'green' && colour != 'blue' && colour != 'white' && colour != 'black') {
        colour = 'red';
    }
    linecolourmenu.set("label", '<img src="'+server_config.image_path+'line'+colour+'.gif" />');
    linecolourmenu.set("value", colour);
    changelinecolour();
}

function nextlinecolour() {
    switch (getcurrentlinecolour()) {
    case 'red': setcurrentlinecolour('yellow'); break;
    case 'yellow': setcurrentlinecolour('green'); break;
    case 'green': setcurrentlinecolour('blue'); break;
    case 'blue': setcurrentlinecolour('white'); break;
    case 'white': setcurrentlinecolour('black'); break;
    }
}

function prevlinecolour() {
    switch (getcurrentlinecolour()) {
    case 'yellow': setcurrentlinecolour('red'); break;
    case 'green': setcurrentlinecolour('yellow'); break;
    case 'blue': setcurrentlinecolour('green'); break;
    case 'white': setcurrentlinecolour('blue'); break;
    case 'black': setcurrentlinecolour('white'); break;
    }
}

function setlinecolour(colour, line, currenttool) {
    if (line) {
        var rgb;
        if (currenttool == 'highlight') {
            if (colour == "yellow") { rgb = "#ffffb0"; }
            else if (colour == "green") { rgb = "#b0ffb0"; }
            else if (colour == "blue") { rgb = "#d0d0ff"; }
            else if (colour == "white") { rgb = "#ffffff"; }
            else if (colour == "black") { rgb = "#323232"; }
            else { rgb = "#ffb0b0"; } // Red
            line.attr("fill", rgb);
            line.attr("opacity", 0.5);
        } else {
            if (colour == "yellow") { rgb = "#ff0"; }
            else if (colour == "green") { rgb = "#0f0"; }
            else if (colour == "blue") { rgb = "#00f"; }
            else if (colour == "white") { rgb = "#fff"; }
            else if (colour == "black") { rgb = "#000"; }
            else { rgb = "#f00"; } // Red
        }
        line.attr("stroke", rgb);
    }
}

function changelinecolour(e) {
    /*
     if ($defined(lineselect)) {
     var canvas = document.id(lineselect.paper.canvas);
     if (!lineselectid) {
     // Cannot change the colour before the server has responded
     setcurrentlinecolour(canvas.retrieve("colour"));
     } else {
     var canvas = document.id(lineselect.paper.canvas);
     var line = canvas.retrieve("line");
     var colour = getcurrentlinecolour();
     if (canvas.retrieve("colour") != colour) {
     setlinecolour(colour, line);
     canvas.store("colour", colour);
     server.addannotation({type: "line", colour: colour, id: canvas.retrieve("id"), coords: {sx:-1,sy:-1,ex:-1,ey:-1} }, canvas);
     }
     }
     }
     */
    if (!server.editing) {
        return;
    }
    Cookie.write('uploadpdf_linecolour', getcurrentlinecolour());
}

function getcurrentstamp() {
    if (!server.editing) {
        return '';
    }
    return stampmenu.get("value");
}

function getstampimage(stamp) {
    return server_config.image_path+'stamps/'+stamp+'.png';
}

function setcurrentstamp(stamp) {
    if (!server.editing) {
        return;
    }
    // Check valid stamp?
    stampmenu.set("label", '<img width="32" height="32" src="'+getstampimage(stamp)+'" />');
    stampmenu.set("value", stamp);
    changestamp();
}

function changestamp(e) {
    if (!server.editing) {
        return;
    }
    Cookie.write('uploadpdf_stamp', getcurrentstamp());
    setcurrenttool('stamp');
}

function getcurrenttool() {
    if (!server.editing) {
        return 'comment';
    }
    return choosedrawingtool.get("value").replace('icon','');
}

function setcurrenttool(toolname) {
    if (!server.editing) {
        return;
    }
    abortline(); // Just in case we are in the middle of drawing, when we change tools
    toolname += 'icon';
    var btns = choosedrawingtool.getButtons();
    var count = choosedrawingtool.getCount();
    var idx = 0;
    for (var i=0; i<count; i++) {
        if (btns[i].get("value") == toolname) {
            idx = i;
        }
    }
    choosedrawingtool.check(idx);
    choosedrawingtool.set('value',btns[idx].get('value'));
}

function startline(e) {
    if (!server.editing) {
        return true;
    }
    if (e.rightClick) {
        return true;
    }

    //unselectline();

    if (currentpaper) {
        return true; // If user clicks very quickly this can happen
    }

    var tool = getcurrenttool();

    modifier = Browser.Platform.mac ? e.alt : e.control;
    if (tool == 'comment' && !modifier) {
        return true;
    }
    if (tool == 'erase') {
        return true;
    }

    if ($defined(currentcomment)) {
        updatelastcomment();
        return true;
    }

    context_quicklist.hide();
    context_comment.hide();

    e.preventDefault(); // Stop FF from dragging the image

    var dims = document.id('pdfimg').getCoordinates();
    var sx = e.page.x - dims.left;
    var sy = e.page.y - dims.top;

    currentpaper = Raphael(dims.left,dims.top,dims.width,dims.height);
    document.id(document).addEvent('mousemove', updateline);
    linestartpos = {x: sx, y: sy};
    if (tool == 'freehand') {
        freehandpoints = new Array({x:linestartpos.x, y:linestartpos.y});
    }
    if (tool == 'stamp') {
        document.id(document).addEvent('mouseup', finishline); // Click without move = default sized stamp
    }

    return false;
}

function updateline(e) {
    if (!server.editing) {
        return;
    }

    var dims = document.id('pdfimg').getCoordinates();
    var ex = e.page.x - dims.left;
    var ey = e.page.y - dims.top;

    if (ex > dims.width) {
        ex = dims.width;
    }
    if (ex < 0) {
        ex = 0;
    }
    if (ey > dims.height) {
        ey = dims.height;
    }
    if (ey < 0) {
        ey = 0;
    }

    var currenttool = getcurrenttool();

    if ($defined(currentline) && currenttool != 'freehand') {
        currentline.remove();
    } else {
        // Doing this earlier catches the starting mouse click by mistake
        document.id(document).addEvent('mouseup',finishline);
    }

    switch (currenttool) {
    case 'rectangle':
        var w = Math.abs(ex-linestartpos.x);
        var h = Math.abs(ey-linestartpos.y);
        var sx = Math.min(linestartpos.x, ex);
        var sy = Math.min(linestartpos.y, ey);
        currentline = currentpaper.rect(sx, sy, w, h);
        break;
    case 'oval':
        var rx = Math.abs(ex - linestartpos.x) / 2;
        var ry = Math.abs(ey - linestartpos.y) / 2;
        var sx = Math.min(linestartpos.x, ex) + rx; // Add 'rx'/'ry' to get to the middle
        var sy = Math.min(linestartpos.y, ey) + ry;
        currentline = currentpaper.ellipse(sx, sy, rx, ry);
        break;
    case 'freehand':
        var dx = linestartpos.x-ex;
        var dy = linestartpos.y-ey;
        var dist = Math.sqrt(dx*dx+dy*dy);
        if (dist < 2) { // Trying to reduce the number of points a bit
            return;
        }
        currentline = currentpaper.path("M "+linestartpos.x+" "+linestartpos.y+"L"+ex+" "+ey);
        freehandpoints.push({x:ex, y:ey});
        linestartpos.x = ex;
        linestartpos.y = ey;
        break;
    case 'highlight':
        var w = Math.abs(ex-linestartpos.x);
        var h = HIGHLIGHT_LINEWIDTH;
        var sx = Math.min(linestartpos.x, ex);
        var sy = linestartpos.y - 0.5 * HIGHLIGHT_LINEWIDTH;
        currentline = currentpaper.rect(sx, sy, w, h);
        break;
    case 'stamp':
        var w = Math.abs(ex-linestartpos.x);
        var h = Math.abs(ey-linestartpos.y);
        var sx = Math.min(linestartpos.x, ex);
        var sy = Math.min(linestartpos.y, ey);
        currentline = currentpaper.image(getstampimage(getcurrentstamp()), sx, sy, w, h);
        break;
    default: // Comment + Ctrl OR line
        currentline = currentpaper.path("M "+linestartpos.x+" "+linestartpos.y+"L"+ex+" "+ey);
        break;
    }
    if (currenttool == 'highlight') {
        currentline.attr("stroke-width", 0);
    } else {
        currentline.attr("stroke-width", LINEWIDTH);
    }
    setlinecolour(getcurrentlinecolour(), currentline, currenttool);
}

function finishline(e) {
    if (!server.editing) {
        return;
    }
    document.id(document).removeEvent('mousemove', updateline);
    document.id(document).removeEvent('mouseup', finishline);

    if (!$defined(currentpaper)) {
        return;
    }

    var dims = document.id('pdfimg').getCoordinates();
    var coords;
    var tool = getcurrenttool();
    if (tool == 'freehand') {
        coords = freehandpoints;
    } else {
        coords = {sx:linestartpos.x, sy:linestartpos.y, ex: (e.page.x-dims.left), ey: (e.page.y-dims.top)};
        if (coords.ex > dims.width) {
            coords.ex = dims.width;
        }
        if (coords.ex < 0) {
            coords.ex = 0;
        }
        if (coords.ey > dims.height) {
            coords.ey = dims.height;
        }
        if (coords.ey < 0) {
            coords.ey = 0;
        }
    }

    currentpaper.remove();
    currentpaper = null;
    currentline = null;

    makeline(coords, tool);
}

function abortline() {
    if (!server.editing) {
        return;
    }
    if (currentline) {
        document.id(document).removeEvent('mousemove', updateline);
        document.id(document).removeEvent('mouseup', finishline);
        if ($defined(currentpaper)) {
            currentpaper.remove();
            currentpaper = null;
        }
        currentline = null;
    }
}

function makeline(coords, type, id, colour, stamp) {
    var linewidth = LINEWIDTH;
    if (type == 'highlight') {
        linewidth = 0;
    }
    var halflinewidth = linewidth * 0.5;
    var dims = document.id('pdfimg').getCoordinates();
    var paper;
    var line;
    var details;
    var boundary;
    var container = new Element('span');

    if (!$defined(colour)) {
        colour = getcurrentlinecolour();
    }
    details = {type: type, colour: colour};

    if (type == 'freehand') {
        details.path = coords[0].x+','+coords[0].y;
        for (var i=1; i<coords.length; i++) {
            details.path += ','+coords[i].x+','+coords[i].y;
        }

        var maxx = minx = coords[0].x;
        var maxy = miny = coords[0].y;
        for (var i=1; i<coords.length; i++) {
            minx = Math.min(minx, coords[i].x);
            maxx = Math.max(maxx, coords[i].x);
            miny = Math.min(miny, coords[i].y);
            maxy = Math.max(maxy, coords[i].y);
        }
        boundary = {x: (minx-(halflinewidth*0.5)), y: (miny-(halflinewidth*0.5)), w: (maxx+linewidth-minx), h: (maxy+linewidth-miny)};
        if (boundary.h < 14) {
            boundary.h = 14;
        }
        if (Browser.ie && Browser.version < 9) {
            // Does not work with FF & Moodle
            container.setStyles({ left: boundary.x, top: boundary.y, width: boundary.w+2, height: boundary.h+2, position: 'absolute' });
        } else {
            // Does not work with IE
            container.set('style', 'position:absolute; top:'+boundary.y+'px; left:'+boundary.x+'px; width:'+(boundary.w+2)+'px; height:'+(boundary.h+2)+'px;');
        }
        document.id('pdfholder').adopt(container);
        paper = Raphael(container);
        minx -= halflinewidth;
        miny -= halflinewidth;

        var pathstr = 'M'+(coords[0].x-minx)+' '+(coords[0].y-miny);
        for (var i=1; i<coords.length; i++) {
            pathstr += 'L'+(coords[i].x-minx)+' '+(coords[i].y-miny);
        }
        line = paper.path(pathstr);
    } else {
        if (type == 'stamp') {
            if (Math.abs(coords.sx - coords.ex) < 4 && Math.abs(coords.sy - coords.ey) < 4) {
                coords.ex = coords.sx + 40;
                coords.ey = coords.sy + 40;
            }
        }
        details.coords = { sx: coords.sx, sy: coords.sy, ex: coords.ex, ey: coords.ey };

        if (coords.sx > coords.ex) { // Always go left->right
            var temp = coords.sx; coords.sx = coords.ex; coords.ex = temp;
            temp = coords.sy;     coords.sy = coords.ey; coords.ey = temp;
        }
        if (type == 'highlight') {
            coords.sy -= HIGHLIGHT_LINEWIDTH * 0.5;
            coords.ey = coords.sy + HIGHLIGHT_LINEWIDTH;
        }
        if (coords.sy < coords.ey) {
            boundary = {x: (coords.sx-(halflinewidth*0.5)), y: (coords.sy-(halflinewidth*0.5)), w: (coords.ex+linewidth-coords.sx), h: (coords.ey+linewidth-coords.sy)};
            coords.sy = halflinewidth; coords.ey = boundary.h - halflinewidth;
        } else {
            boundary = {x: (coords.sx-(halflinewidth*0.5)), y: (coords.ey-(halflinewidth*0.5)), w: (coords.ex+linewidth-coords.sx), h: (coords.sy+linewidth-coords.ey)};
            coords.sy = boundary.h - halflinewidth; coords.ey = halflinewidth;
        }
        coords.sx = halflinewidth; coords.ex = boundary.w - halflinewidth;
        if (boundary.h < 14) {
            boundary.h = 14;
        }
        if (Browser.ie && Browser.version < 9) {
            // Does not work with FF & Moodle
            container.setStyles({ left: boundary.x, top: boundary.y, width: boundary.w+2, height: boundary.h+2, position: 'absolute' });
        } else {
            // Does not work with IE
            container.set('style', 'position:absolute; top:'+boundary.y+'px; left:'+boundary.x+'px; width:'+(boundary.w+2)+'px; height:'+(boundary.h+2)+'px;');
        }
        document.id('pdfholder').adopt(container);
        paper = Raphael(container);
        switch (type) {
        case 'rectangle':
            var w = Math.abs(coords.ex - coords.sx);
            var h = Math.abs(coords.ey - coords.sy);
            var sx = Math.min(coords.sx, coords.ex);
            var sy = Math.min(coords.sy, coords.ey);
            line = paper.rect(sx, sy, w, h);
            break;
        case 'oval':
            var rx = Math.abs(coords.ex - coords.sx) / 2;
            var ry = Math.abs(coords.ey - coords.sy) / 2;
            var sx = Math.min(coords.sx, coords.ex)+rx;
            var sy = Math.min(coords.sy, coords.ey)+ry;
            line = paper.ellipse(sx, sy, rx, ry);
            break;
        case 'highlight':
            var w = Math.abs(coords.ex - coords.sx);
            var h = HIGHLIGHT_LINEWIDTH;
            var sx = Math.min(coords.sx, coords.ex);
            var sy = Math.min(coords.sy, coords.ey);
            line = paper.rect(sx, sy, w, h);
            break;
        case 'stamp':
            var w = Math.abs(coords.ex - coords.sx);
            var h = Math.abs(coords.ey - coords.sy);
            var sx = Math.min(coords.sx, coords.ex);
            var sy = Math.min(coords.sy, coords.ey);
            if (!$defined(stamp)) {
                stamp = getcurrentstamp();
            }
            line = paper.image(getstampimage(stamp), sx, sy, w, h);
            details.path = stamp;
            break;
        default:
            line = paper.path("M "+coords.sx+" "+coords.sy+" L "+coords.ex+" "+coords.ey);
            details.type = 'line';
            break;
        }
    }
    line.attr("stroke-width", linewidth);
    setlinecolour(colour, line, type);

    var domcanvas = document.id(paper.canvas);

    domcanvas.store('container',container);
    domcanvas.store('width',boundary.w);
    domcanvas.store('height',boundary.h);
    domcanvas.store('line',line);
    domcanvas.store('colour',colour);
    if (server.editing) {
        domcanvas.addEvent('mousedown',startline);
        domcanvas.addEvent('click', eraseline);
        if ($defined(id)) {
            domcanvas.store('id',id);
        } else {
            server.addannotation(details, domcanvas);
        }
    } else {
        domcanvas.store('id',id);
    }

    allannotations.push(container);
}

/*
 function selectline(e) {
 var paper = this.retrieve('paper'); // Note this will not work
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
 */

function eraseline(e) {
    if (!server.editing) {
        return;
    }
    if (getcurrenttool() != 'erase') {
        return false;
    }

    var id = this.retrieve('id');
    if (id) {
        var container = this.retrieve('container');
        allannotations.erase(container);
        container.destroy();
        server.removeannotation(id);
    }

    return true;
}

function keyboardnavigation(e) {
    if ($defined(currentcomment)) {
        return; // No keyboard navigation when editing comments
    }

    var modifier = Browser.Platform.mac ? e.alt : e.control;

    if (e.key == 'n') {
        gotonextpage();
    } else if (e.key == 'p') {
        gotoprevpage();
    }
    if (server.editing) {
        if (e.key == 'c') {
            setcurrenttool('comment');
        } else if (e.key == 'l') {
            setcurrenttool('line');
        } else if (e.key == 'r') {
            setcurrenttool('rectangle');
        } else if (e.key == 'o') {
            setcurrenttool('oval');
        } else if (e.key == 'f') {
            setcurrenttool('freehand');
        } else if (e.key == 'h') {
            setcurrenttool('highlight');
        } else if (e.key == 's') {
            setcurrenttool('stamp');
        } else if (e.key == 'e') {
            setcurrenttool('erase');
        } else if (e.key == 'g' && modifier) {
            // TODO - get this working (at some point)
            //var btn = document.id('generateresponse');
            //var frm = btn.parentNode;
            //frm.submit();
        } else if (e.code == 219) {  // { or [
            if (e.shift) {
                prevlinecolour();
            } else {
                prevcommentcolour();
            }
        } else if (e.code == 221) {  // } or ]
            if (e.shift) {
                nextlinecolour();
            } else {
                nextcommentcolour();
            }
        }
    }
}

function updatefindcomments(page, id, text) {
    if (!server.editing) {
        return;
    }
    if (text.length > 40) {
        text = page+': '+text.substring(0, 39) + '&hellip;';
    } else {
        text = page+': '+text;
    }
    var value = page+':'+id;
    var menu = findcommentsmenu.getMenu();
    var items = menu.getItems();
    for (var i=0; i<items.length; i++) {
        if (!$defined(items[i].value)) {
            continue;
        }
        var details = items[i].value.split(':');
        var itempage = details[0].toInt();
        var itemid = details[1];
        if (itemid == 0) { // 'No comments'
            items[i].value = page+':'+id;
            items[i].cfg.setProperty('text', text);
            return;
        }
        if (itemid == id) {
            items[i].cfg.setProperty('text', text);
            return;
        }
        if (itempage > page) {
            menu.insertItem({text: text, value: value}, i.toInt());
            return;
        }
    }
    menu.addItem({text: text, value: value});
}

function removefromfindcomments(id) {
    if (!server.editing) {
        return;
    }
    var menu = findcommentsmenu.getMenu();
    var items = menu.getItems();
    for (var i=0; i<items.length; i++) {
        if (!$defined(items[i].value)) {
            continue;
        }
        var itemid = items[i].value.split(':')[1];
        if (itemid == id) {
            if (items.length == 1) {
                // Only item in list - set it to 'no comments'
                items[i].cfg.setProperty('text', server_config.lang_nocomments);
                items[i].value = '0:0';
            } else {
                menu.removeItem(items[i]);
            }
            return;
        }
    }
}

function doscrolltocomment(commentid) {
    if (commentid == 0) {
        return;
    }
    if (lasthighlight) {
        lasthighlight.removeClass('comment-highlight');
        lasthighlight = null;
    }
    var comments = document.id('pdfholder').getElements('.comment');
    comments.each( function(comment) {
        if (comment.retrieve('id') == commentid) {
            comment.addClass('comment-highlight');
            lasthighlight = comment;

            var dims = comment.getCoordinates();
            var win = window.getCoordinates();
            var scroll = window.getScroll();
            var view = win;
            view.right += scroll.x;
            view.bottom += scroll.y;

            var scrolltocoord = {x:scroll.x, y:scroll.y}

            if (view.right < (dims.right+10)) {
                if ((dims.width + 20) < win.width) {
                    // Scroll right of comment onto the screen (if it will all fit)
                    scrolltocoord.x = dims.right + 10 - win.width;
                } else {
                    // Just scroll the left of the comment onto the screen
                    scrolltocoord.x = dims.left - 10;
                }
            }

            if (view.bottom < (dims.bottom+10)) {
                if ((dims.height + 20) < win.height) {
                    // Scroll bottom of comment onto the screen (if it will all fit)
                    scrolltocoord.y = dims.bottom + 10 - win.height;
                } else {
                    // Just scroll top of comment onto the screen
                    scrolltocoord.y = dims.top - 10;
                }
            }

            window.scrollTo(scrolltocoord.x, scrolltocoord.y);

            return;
        }
    });
}

function startjs() {
    new Asset.css('style/annotate.css');
    new Asset.css(server_config.css_path+'menu.css');
    new Asset.css(server_config.css_path+'button.css');

    server = new ServerComm(server_config);

    document.body.className += ' yui-skin-sam';

    if (server.editing) {
        if (document.getElementById('choosecolour')) {
            colourMenu = new YAHOO.widget.Button("choosecolour", {
                type: "menu",
                menu: "choosecolourmenu",
                lazyloadmenu: false });
            colourMenu.on("selectedMenuItemChange", function(e) {
                var menuItem = e.newValue;
                var colour = (/choosecolour-([a-z]*)-/i.exec(menuItem.element.className))[1];
                this.set("label", '<img src="'+server_config.image_path+colour+'.gif" />');
                this.set("value", colour);
                changecolour();
            });
        }
        if (document.getElementById('chooselinecolour')) {
            linecolourmenu = new YAHOO.widget.Button("chooselinecolour", {
                type: "menu",
                menu: "chooselinecolourmenu",
                lazyloadmenu: false });
            linecolourmenu.on("selectedMenuItemChange", function(e) {
                var menuItem = e.newValue;
                var colour = (/choosecolour-([a-z]*)-/i.exec(menuItem.element.className))[1];
                this.set("label", '<img src="'+server_config.image_path+'line'+colour+'.gif" />');
                this.set("value", colour);
                changelinecolour();
            });
        }
        if (document.getElementById('choosestamp')) {
            stampmenu = new YAHOO.widget.Button("choosestamp", {
                type: "menu",
                menu: "choosestampmenu",
                lazyloadmenu: false });
            stampmenu.on("selectedMenuItemChange", function(e) {
                var menuItem = e.newValue;
                var stamp = (/choosestamp-([a-z]*)-/i.exec(menuItem.element.className))[1];
                this.set("label", '<img width="32" height="32" src="'+getstampimage(stamp)+'" />');
                this.set("value", stamp);
                changestamp();
            });
        }
        if (document.getElementById('showpreviousbutton')) {
            var showPreviousMenu = new YAHOO.widget.Button("showpreviousbutton", {
                type: "menu",
                menu: "showpreviousselect",
                lazyloadmenu: false });
            showPreviousMenu.on("selectedMenuItemChange", function(e) {
                var compareid = e.newValue.value;
                var url = 'editcomment.php?id='+server.id+'&userid='+server.userid+'&pageno='+server.pageno;
                if (compareid > -1) {
                    url += '&topframe=1&showprevious='+compareid;
                }
                top.location = url;
            });
        }
        if (document.getElementById('savedraft')) {
            var savedraftbutton = new YAHOO.widget.Button("savedraft");
        }
        if (document.getElementById('generateresponse')) {
            var generateresponsebutton = new YAHOO.widget.Button("generateresponse");
        }
        if (document.getElementById('choosetoolgroup')) {
            choosedrawingtool = new YAHOO.widget.ButtonGroup("choosetoolgroup");
            setcurrenttool('commenticon');
        }
    }
    var downloadpdfbutton = new YAHOO.widget.Button("downloadpdf");
    prevbutton = new YAHOO.widget.Button("prevpage");
    prevbutton.on("click", gotoprevpage);
    nextbutton = new YAHOO.widget.Button("nextpage");
    nextbutton.on("click", gotonextpage);
    findcommentsmenu = new YAHOO.widget.Button("findcommentsbutton", {
        type: "menu",
        menu: "findcommentsselect",
        lazyloadmenu: false });
    findcommentsmenu.on("selectedMenuItemChange", function(e) {
        var menuval = e.newValue.value;
        pageno = menuval.split(':')[0].toInt();
        commentid = menuval.split(':')[1].toInt();
        if (pageno > 0) {
            if (server.pageno.toInt() == pageno) {
                doscrolltocomment(commentid);
            } else {
                server.scrolltocomment(commentid);
                gotopage(pageno);
            }
        }
    });

    server.getcomments();

    if (server.editing) {
        document.id('pdfimg').addEvent('click', addcomment);
        document.id('pdfimg').addEvent('mousedown', startline);
        document.id('pdfimg').ondragstart = function() { return false; }; // To stop ie trying to drag the image
        var colour = Cookie.read('uploadpdf_colour');
        if (!$defined(colour)) {
            colour = 'yellow';
        }
        setcurrentcolour(colour);
        var linecolour = Cookie.read('uploadpdf_linecolour');
        if (!$defined(linecolour)) {
            linecolour = 'red';
        }
        setcurrentlinecolour(linecolour);
        var stamp = Cookie.read('uploadpdf_stamp');
        if (!$defined(stamp)) {
            stamp = 'tick';
        }
        setcurrentstamp(stamp);
    }

    // Start preloading pages if using js navigation method
    if (server_config.js_navigation) {
        document.addEvent('keydown', keyboardnavigation);
        pagelist = new Array();
        var pageno = server.pageno.toInt();
        // Little fix as Firefox remembers the selected option after a page refresh
        var sel = document.id('selectpage');
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
    if (!server.editing) {
        return;
    }

    if (context_quicklist.quickcount == 0) {
        if (!context_quicklist.menu.getElement('a[href$=noitems]')) {
            context_quicklist.addItem('noitems', server_config.lang_emptyquicklist+' &#0133;', null, function() { alert(server_config.lang_emptyquicklist_instructions); });
        }
    } else {
        context_quicklist.removeItem('noitems');
    }
}

function addtoquicklist(item) {
    if (!server.editing) {
        return;
    }
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
        var imgpos = document.id('pdfimg').getPosition();
        var pos = new Object();
        pos.x = menu.menu.getStyle('left').toInt() - imgpos.x;
        pos.y = menu.menu.getStyle('top').toInt() - imgpos.y + 20;
        // Nasty hack to reposition the comment box in IE
        if (Browser.ie && Browser.version < 9) {
            if (Browser.ie6 || Browser.ie7) {
                pos.x += 40;
                pos.y -= 20;
            } else {
                pos.y -= 15;
            }
        }
        var cb = makecommentbox(pos, quicklist[id].text, quicklist[id].colour);
        if (Browser.ie && Browser.version < 9) {
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
    if (!server.editing) {
        return;
    }
    context_quicklist.removeItem(itemid);
    context_quicklist.quickcount--;
    context_quicklistnoitems();
}

function initcontextmenu() {
    if (!server.editing) {
        return;
    }
    var offs;
    var content = document.id('region-main');
    if (content) {
        offs = content.getPosition();
        offs.x = -offs.x;
        offs.y = -offs.y;
    } else {
        offs = {x:0, y:0}
    }

    //create a context menu
    context_quicklist = new ContextMenu({
        targets: null,
        menu: 'context-quicklist',
        actions: {
            removeitem: function(itemid, menu) {
                server.removefromquicklist(itemid);
            }
        },
        offsets: offs
    });
    context_quicklist.addmenu(document.id('pdfimg'));
    context_quicklist.quickcount = 0;
    context_quicklistnoitems();
    quicklist = new Array();

    if (Browser.ie6 || Browser.ie7) {
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
        },
        offsets: offs
    });

    server.getquicklist();
}

function showpage(pageno) {
    var pdfsize = document.id('pdfsize');
    if (Browser.ie && Browser.version < 9) {
        // Does not work with FF & Moodle
        pdfsize.setStyle('width',pagelist[pageno].width);
        pdfsize.setStyle('height',pagelist[pageno].height);
    } else {
        // Does not work with IE
        var style = 'height:'+pagelist[pageno].height+'px; width:'+pagelist[pageno].width+'px;'+' clear: both;';
        pdfsize.set('style',style);
    }
    var pdfimg = document.id('pdfimg');
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
        document.id('pdfimg').setProperty('src',pagelist[pageno].url);
    } else {
        setTimeout('check_pageimage('+pageno+')', 200);
    }
}

function gotopage(pageno) {
    var pagecount = server_config.pagecount.toInt();
    if ((pageno <= pagecount) && (pageno > 0)) {
        document.id('pdfholder').getElements('.comment').destroy(); // Destroy all the currently displayed comments
        allannotations.each(function(p) { p.destroy(); });
        allannotations.empty();
        abortline(); // Abandon any lines currently being drawn
        currentcomment = null; // Throw away any comments in progress
        editbox = null;
        lasthighlight = null;

        // Set the dropdown selects to have the correct page number in them
        var el = document.id('selectpage');
        var i;
        for (i=0; i<el.length; i++) {
            if (el[i].value == pageno) {
                el.selectedIndex = i;
                break;
            }
        }
        el = document.id('selectpage2');
        for (i=0; i<el.length; i++) {
            if (el[i].value == pageno) {
                el.selectedIndex = i;
                break;
            }
        }

        if (server.editing) {
            // Update the 'open in new window' link
            var opennew = document.id('opennewwindow');
            var on_link = opennew.get('href').replace(/pageno=\d+/,"pageno="+pageno);
            opennew.set('href', on_link);
        }

        //Update the next/previous buttons
        if (pageno == pagecount) {
            nextbutton.set('disabled', true);
            document.id('nextpage2').set('disabled', 'disabled');
        } else {
            nextbutton.set('disabled', false);
            document.id('nextpage2').erase('disabled');
        }
        if (pageno == 1) {
            prevbutton.set('disabled', true);
            document.id('prevpage2').set('disabled', 'disabled');
        } else {
            prevbutton.set('disabled', false);
            document.id('prevpage2').erase('disabled');
        }

        server.pageno = ""+pageno;
        server.pageloadcount++;
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
    var el = document.id('selectpage');
    var idx = el.selectedIndex;
    gotopage(el[idx].value);
}

function selectpage2() {
    var el = document.id('selectpage2');
    var idx = el.selectedIndex;
    gotopage(el[idx].value);
}

function uploadpdf_init() {
    startjs();
    initcontextmenu();
}
