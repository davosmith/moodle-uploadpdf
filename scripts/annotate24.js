/*global Class, Events, Element, Request, Browser, Cookie, ContextMenu*/ // MooTools classes
/*global Raphael*/
/*global document, confirm, alert, Image, window, top, setTimeout */ // Standard Javascript elements
/*global server_config */
function uploadpdf_init(Y) {
    "use strict";
    Y.Get.js([
        'scripts/mootools-core-1.4.1.js',
        'scripts/mootools-more-1.4.0.1.js',
        'scripts/contextmenu.js',
        'scripts/raphael-min.js'],
        function () {

            var YH, currentcomment, editbox, resizing, server, context_quicklist, context_comment, quicklist,
                pagelist, waitingforpage, pagestopreload, pagesremaining, pageunloading, lasthighlight, colourmenu, linecolourmenu,
                nextbutton, prevbutton, choosedrawingtool, findcommentsmenu, stampmenu, resendtimeout, currentpaper, currentline,
                linestartpos, freehandpoints, allannotations, LINEWIDTH, HIGHLIGHT_LINEWIDTH, $defined, ServerComm;

            if (typeof YAHOO === 'undefined') {
                YH = Y.YUI2;
            } else {
                YH = YAHOO;
            }

            currentcomment = null; // The comment that is currently being edited
            editbox = null; // The edit box that is currently displayed
            resizing = false; // A box is being resized (so disable dragging)
            server = null; // The object use to send data back to the server
            context_quicklist = null;
            context_comment = null;
            quicklist = null; // Stores all the comments in the quicklist
            pagelist = []; // Stores all the data for the preloaded pages
            waitingforpage = -1;  // Waiting for this page from the server - display as soon as it is received
            pagestopreload = 4; // How many pages ahead to load when you hit a non-preloaded page
            pagesremaining = pagestopreload; // How many more pages to preload before waiting
            pageunloading = false;
            lasthighlight = null;

            colourmenu = null;
            linecolourmenu = null;
            nextbutton = null;
            prevbutton = null;
            choosedrawingtool = null;
            findcommentsmenu = null;
            stampmenu = null;

            resendtimeout = 4000;

// All to do with line drawing
            currentpaper = null;
            currentline = null;
            linestartpos = null;
            freehandpoints = null;

            allannotations = [];

            LINEWIDTH = 3.0;
            HIGHLIGHT_LINEWIDTH = 14.0;

            $defined = function (obj) { return (obj !== undefined && obj !== null); };

            function hidesendfailed() {
                document.id('sendagain').removeEvents();
                document.id('sendfailed').setStyle('display', 'none');
            }

            function showsendfailed(resend) {
                if (pageunloading) {
                    return;
                }

                // If less than 2 failed messages since the last successful
                // message, then try again immediately
                if (server.retrycount < 2) {
                    server.retrycount += 1;
                    resend();
                    return;
                }

                var el = document.id('sendagain');
                el.addEvent('click', resend);
                el.addEvent('click', hidesendfailed);
                document.id('sendfailed').setStyles({display: 'block', position: 'absolute', top: 200, left: 200, 'z-index': 9999, 'background-color': '#d0d0d0', 'border': 'black 1px solid', padding: 10});
            }

            function showpage(pageno) {
                var pdfsize, style, pdfimg;
                pdfsize = document.id('pdfsize');
                if (Browser.ie && Browser.version < 9) {
                    // Does not work with FF & Moodle
                    pdfsize.setStyle('width', pagelist[pageno].width);
                    pdfsize.setStyle('height', pagelist[pageno].height);
                } else {
                    // Does not work with IE
                    style = 'height:' + pagelist[pageno].height + 'px; width:' + pagelist[pageno].width + 'px;' + ' clear: both;';
                    pdfsize.set('style', style);
                }
                pdfimg = document.id('pdfimg');
                pdfimg.setProperty('width', pagelist[pageno].width);
                pdfimg.setProperty('height', pagelist[pageno].height);
                if (pagelist[pageno].image.complete) {
                    pdfimg.setProperty('src', pagelist[pageno].url);
                } else {
                    pdfimg.setProperty('src', server_config.blank_image);
                    setTimeout(function () { check_pageimage(pageno); }, 200);
                }
                server.getcomments();
            }

            function updatepagenavigation(pageno) {
                var pagecount, el, i, opennew, on_link;
                pageno = parseInt(pageno, 10);
                pagecount = server_config.pagecount.toInt();

                // Set the dropdown selects to have the correct page number in them
                el = document.id('selectpage');
                for (i = 0; i < el.length; i += 1) {
                    if (parseInt(el[i].value, 10) === pageno) {
                        el.selectedIndex = i;
                        break;
                    }
                }
                el = document.id('selectpage2');
                for (i = 0; i < el.length; i += 1) {
                    if (parseInt(el[i].value, 10) === pageno) {
                        el.selectedIndex = i;
                        break;
                    }
                }

                if (server.editing) {
                    // Update the 'open in new window' link
                    opennew = document.id('opennewwindow');
                    on_link = opennew.get('href').replace(/pageno=\d+/, "pageno=" + pageno);
                    opennew.set('href', on_link);
                }

                //Update the next/previous buttons
                if (pageno === pagecount) {
                    nextbutton.set('disabled', true);
                    document.id('nextpage2').set('disabled', 'disabled');
                } else {
                    nextbutton.set('disabled', false);
                    document.id('nextpage2').erase('disabled');
                }
                if (pageno === 1) {
                    prevbutton.set('disabled', true);
                    document.id('prevpage2').set('disabled', 'disabled');
                } else {
                    prevbutton.set('disabled', false);
                    document.id('prevpage2').erase('disabled');
                }
            }

            function gotopage(pageno) {
                var pagecount;
                pageno = parseInt(pageno, 10);
                pagecount = server_config.pagecount.toInt();
                if ((pageno <= pagecount) && (pageno > 0)) {
                    document.id('pdfholder').getElements('.comment').destroy(); // Destroy all the currently displayed comments
                    allannotations.each(function (p) { p.destroy(); });
                    allannotations.empty();
                    abortline(); // Abandon any lines currently being drawn
                    currentcomment = null; // Throw away any comments in progress
                    editbox = null;
                    lasthighlight = null;

                    updatepagenavigation(pageno);

                    server.pageno = "" + pageno;
                    server.pageloadcount += 1;
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
                var el, idx;
                el = document.id('selectpage');
                idx = el.selectedIndex;
                gotopage(el[idx].value);
            }

            function selectpage2() {
                var el, idx;
                el = document.id('selectpage2');
                idx = el.selectedIndex;
                gotopage(el[idx].value);
            }

            function updatefindcomments(page, id, text) {
                if (!server.editing) {
                    return;
                }
                if (text.length > 40) {
                    text = page + ': ' + text.substring(0, 39) + '&hellip;';
                } else {
                    text = page + ': ' + text;
                }

                var value, menu, items, i, details, itempage, itemid;

                id = parseInt(id, 10);
                page = parseInt(page, 10);
                value = page + ':' + id;
                menu = findcommentsmenu.getMenu();
                items = menu.getItems();
                for (i = 0; i < items.length; i += 1) {
                    if ($defined(items[i].value)) {
                        details = items[i].value.split(':');
                        itempage = parseInt(details[0], 10);
                        itemid = parseInt(details[1], 10);
                        if (itemid === 0) { // 'No comments'
                            items[i].value = page + ':' + id;
                            items[i].cfg.setProperty('text', text);
                            return;
                        }
                        if (itemid === id) {
                            items[i].cfg.setProperty('text', text);
                            return;
                        }
                        if (itempage > page) {
                            menu.insertItem({text: text, value: value}, i.toInt());
                            return;
                        }
                    }
                }
                menu.addItem({text: text, value: value});
            }

            function removefromfindcomments(id) {
                if (!server.editing) {
                    return;
                }
                var menu, items, i, itemid;
                id = parseInt(id, 10);
                menu = findcommentsmenu.getMenu();
                items = menu.getItems();
                for (i = 0; i < items.length; i += 1) {
                    if ($defined(items[i].value)) {
                        itemid = parseInt(items[i].value.split(':')[1], 10);
                        if (itemid === id) {
                            if (items.length === 1) {
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
            }

            function setcolourclass(colour, comment) {
                if (comment) {
                    if (colour === 'red') {
                        comment.set('class', 'comment commentred');
                    } else if (colour === 'green') {
                        comment.set('class', 'comment commentgreen');
                    } else if (colour === 'blue') {
                        comment.set('class', 'comment commentblue');
                    } else if (colour === 'white') {
                        comment.set('class', 'comment commentwhite');
                    } else if (colour === 'clear') {
                        comment.set('class', 'comment commentclear');
                    } else {
                        // Default: yellow comment box
                        comment.set('class', 'comment commentyellow');
                        colour = 'yellow';
                    }
                    comment.store('colour', colour);
                }
            }

            function getcurrentcolour() {
                if (!server.editing) {
                    return 'yellow';
                }
                return colourmenu.get("value");
            }

            function changecolour() {
                if (!server.editing) {
                    return;
                }
                if (currentcomment) {
                    var col = getcurrentcolour();
                    if (col !== currentcomment.retrieve('colour')) {
                        setcolourclass(getcurrentcolour(), currentcomment);
                    }
                }
                Cookie.write('uploadpdf_colour', getcurrentcolour());
            }

            function setcurrentcolour(colour) {
                if (!server.editing) {
                    return;
                }
                if (colour !== 'red' && colour !== 'green' && colour !== 'blue' && colour !== 'white' && colour !== 'clear') {
                    colour = 'yellow';
                }
                colourmenu.set("label", '<img src="' + server_config.image_path + colour + '.gif" />');
                colourmenu.set("value", colour);
                changecolour();
            }

            function nextcommentcolour() {
                switch (getcurrentcolour()) {
                case 'red':
                    setcurrentcolour('yellow');
                    break;
                case 'yellow':
                    setcurrentcolour('green');
                    break;
                case 'green':
                    setcurrentcolour('blue');
                    break;
                case 'blue':
                    setcurrentcolour('white');
                    break;
                case 'white':
                    setcurrentcolour('clear');
                    break;
                }
            }

            function prevcommentcolour() {
                switch (getcurrentcolour()) {
                case 'yellow':
                    setcurrentcolour('red');
                    break;
                case 'green':
                    setcurrentcolour('yellow');
                    break;
                case 'blue':
                    setcurrentcolour('green');
                    break;
                case 'white':
                    setcurrentcolour('blue');
                    break;
                case 'clear':
                    setcurrentcolour('white');
                    break;
                }
            }

            function updatecommentcolour(colour, comment) {
                if (!server.editing) {
                    return;
                }
                if (colour !== comment.retrieve('colour')) {
                    setcolourclass(colour, comment);
                    setcurrentcolour(colour);
                    if (comment !== currentcomment) {
                        server.updatecomment(comment);
                    }
                }
            }

            function setcommentcontent(el, content) {
                var resizehandle;
                el.store('rawtext', content);

                // Replace special characters with html entities
                content = content.replace(/</gi, '&lt;');
                content = content.replace(/>/gi, '&gt;');
                if (Browser.ie7) { // Grrr... no 'pre-wrap'
                    content = content.replace(/\n/gi, '<br/>');
                    content = content.replace(/ {2}/gi, ' &nbsp;');
                }
                resizehandle = el.retrieve('resizehandle');
                el.set('html', content);
                el.adopt(resizehandle);
            }

            function typingcomment(e) {
                if (!server.editing) {
                    return;
                }
                if (e.key === 'esc') {
                    updatelastcomment();
                    e.stop();
                }
            }

            function updatelastcomment() {
                if (!server.editing) {
                    return false;
                }
                // Stop trapping 'escape'
                document.removeEvent('keydown', typingcomment);

                var updated, content, id, oldcolour, newcolour;
                updated = false;
                content = null;
                if (editbox !== null) {
                    content = editbox.get('value');
                    editbox.destroy();
                    editbox = null;
                }
                if (currentcomment !== null) {
                    if (content === null || (content.trim() === '')) {
                        id = currentcomment.retrieve('id');
                        if (id !== -1) {
                            server.removecomment(id);
                        }
                        currentcomment.destroy();

                    } else {
                        oldcolour = currentcomment.retrieve('oldcolour');
                        newcolour = currentcomment.retrieve('colour');
                        if ((content === currentcomment.retrieve('rawtext')) && (newcolour === oldcolour)) {
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

            function editcomment(el) {
                if (!server.editing) {
                    return;
                }
                if (currentcomment === el) {
                    return;
                }
                updatelastcomment();

                currentcomment = el;
                var resizehandle, content;
                resizehandle = currentcomment.retrieve('resizehandle');
                currentcomment.set('html', '');
                currentcomment.adopt(resizehandle);
                content = currentcomment.retrieve('rawtext');
                makeeditbox(currentcomment, content);
                setcurrentcolour(currentcomment.retrieve('colour'));
            }

            function makecommentbox(position, content, colour) {
                // Create the comment box
                var newcomment, drag, resizehandle, resize;
                newcomment = new Element('div');
                document.id('pdfholder').adopt(newcomment);

                if (position.x < 0) {
                    position.x = 0;
                }
                if (position.y < 0) {
                    position.y = 0;
                }

                if ($defined(colour)) {
                    setcolourclass(colour, newcomment);
                } else {
                    setcolourclass(getcurrentcolour(), newcomment);
                }
                newcomment.store('oldcolour', colour);
                //newcomment.set('class', 'comment');
                if (Browser.ie && Browser.version < 9) {
                    // Does not work with FF & Moodle
                    newcomment.setStyles({ left: position.x, top: position.y });
                } else {
                    // Does not work with IE
                    newcomment.set('style', 'position:absolute; top:' + position.y + 'px; left:' + position.x + 'px;');
                }
                newcomment.store('id', -1);

                if (server.editing) {
                    if (context_comment) {
                        context_comment.addmenu(newcomment);
                    }

                    drag = newcomment.makeDraggable({
                        container: 'pdfholder',
                        onCancel: editcomment, // Click without drag = edit
                        onStart: function (el) {
                            if (resizing) {
                                el.retrieve('drag').stop();
                            } else if (el.retrieve('id') === -1) {
                                el.retrieve('drag').stop();
                            }
                        },
                        onComplete: function (el) { server.updatecomment(el); }
                    });
                    newcomment.store('drag', drag); // Remember the drag object so  we can switch it on later

                    resizehandle = new Element('div');
                    resizehandle.set('class', 'resizehandle');
                    newcomment.adopt(resizehandle);
                    resize = newcomment.makeResizable({
                        container: 'pdfholder',
                        handle: resizehandle,
                        modifiers: {'x': 'width', 'y': null},
                        onBeforeStart: function () { resizing = true; },
                        onStart: function (el) {
                            // Do not allow resizes on comments that have not yet
                            // got an id from the server (except when still editing
                            // the text, as that is OK)
                            if (!$defined(editbox)) {
                                if (el.retrieve('id') === -1) {
                                    el.retrieve('resize').stop();
                                }
                            }
                        },
                        onComplete: function (el) {
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

            function getcurrenttool() {
                if (!server.editing) {
                    return 'comment';
                }
                return choosedrawingtool.get("value").replace('icon', '');
            }

            function setcurrenttool(toolname) {
                if (!server.editing) {
                    return;
                }
                Cookie.write('uploadpdf_tool', toolname);
                abortline(); // Just in case we are in the middle of drawing, when we change tools
                toolname += 'icon';
                var btns, count, idx, i;
                btns = choosedrawingtool.getButtons();
                count = choosedrawingtool.getCount();
                idx = 0;
                for (i = 0; i < count; i += 1) {
                    if (btns[i].get("value") === toolname) {
                        idx = i;
                    }
                }
                choosedrawingtool.check(idx);
                choosedrawingtool.set('value', btns[idx].get('value'));
            }

            function getcurrentlinecolour() {
                if (!server.editing) {
                    return 'red';
                }
                return linecolourmenu.get("value");
            }

            function changelinecolour() {
                if (!server.editing) {
                    return;
                }
                Cookie.write('uploadpdf_linecolour', getcurrentlinecolour());
            }

            function setcurrentlinecolour(colour) {
                if (!server.editing) {
                    return;
                }
                if (colour !== 'yellow' && colour !== 'green' && colour !== 'blue' && colour !== 'white' && colour !== 'black') {
                    colour = 'red';
                }
                linecolourmenu.set("label", '<img src="' + server_config.image_path + 'line' + colour + '.gif" />');
                linecolourmenu.set("value", colour);
                changelinecolour();
            }

            function nextlinecolour() {
                switch (getcurrentlinecolour()) {
                case 'red':
                    setcurrentlinecolour('yellow');
                    break;
                case 'yellow':
                    setcurrentlinecolour('green');
                    break;
                case 'green':
                    setcurrentlinecolour('blue');
                    break;
                case 'blue':
                    setcurrentlinecolour('white');
                    break;
                case 'white':
                    setcurrentlinecolour('black');
                    break;
                }
            }

            function prevlinecolour() {
                switch (getcurrentlinecolour()) {
                case 'yellow':
                    setcurrentlinecolour('red');
                    break;
                case 'green':
                    setcurrentlinecolour('yellow');
                    break;
                case 'blue':
                    setcurrentlinecolour('green');
                    break;
                case 'white':
                    setcurrentlinecolour('blue');
                    break;
                case 'black':
                    setcurrentlinecolour('white');
                    break;
                }
            }

            function setlinecolour(colour, line, currenttool) {
                if (line) {
                    var rgb;
                    if (currenttool === 'highlight') {
                        if (colour === "yellow") {
                            rgb = "#ffffb0";
                        } else if (colour === "green") {
                            rgb = "#b0ffb0";
                        } else if (colour === "blue") {
                            rgb = "#d0d0ff";
                        } else if (colour === "white") {
                            rgb = "#ffffff";
                        } else if (colour === "black") {
                            rgb = "#323232";
                        } else {
                            rgb = "#ffb0b0"; // Red
                        }
                        line.attr("fill", rgb);
                        line.attr("opacity", 0.5);
                    } else {
                        if (colour === "yellow") {
                            rgb = "#ff0";
                        } else if (colour === "green") {
                            rgb = "#0f0";
                        } else if (colour === "blue") {
                            rgb = "#00f";
                        } else if (colour === "white") {
                            rgb = "#fff";
                        } else if (colour === "black") {
                            rgb = "#000";
                        } else {
                            rgb = "#f00"; // Red
                        }
                    }
                    line.attr("stroke", rgb);
                }
            }

            function getcurrentstamp() {
                if (!server.editing) {
                    return '';
                }
                return stampmenu.get("value");
            }

            function getstampimage(stamp) {
                return server_config.image_path + 'stamps/' + stamp + '.png';
            }

            function changestamp(e) {
                if (!server.editing) {
                    return;
                }
                Cookie.write('uploadpdf_stamp', getcurrentstamp());
                if (e !== false) {
                    setcurrenttool('stamp');
                }
            }

            function setcurrentstamp(stamp, settool) {
                if (!server.editing) {
                    return;
                }
                // Check valid stamp?
                stampmenu.set("label", '<img width="32" height="32" src="' + getstampimage(stamp) + '" />');
                stampmenu.set("value", stamp);
                if (settool === undefined) {
                    settool = true;
                }
                changestamp(settool);
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

                var dims, coords, tool;
                dims = document.id('pdfimg').getCoordinates();
                tool = getcurrenttool();
                if (tool === 'freehand') {
                    coords = freehandpoints;
                } else {
                    coords = {sx: linestartpos.x, sy: linestartpos.y, ex: (e.page.x - dims.left), ey: (e.page.y - dims.top)};
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

            function updateline(e) {
                if (!server.editing) {
                    return;
                }

                var dims, ex, ey, currenttool, w, h, sx, sy, rx, ry, dx, dy, dist;

                dims = document.id('pdfimg').getCoordinates();
                ex = e.page.x - dims.left;
                ey = e.page.y - dims.top;

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

                currenttool = getcurrenttool();

                if ($defined(currentline) && currenttool !== 'freehand') {
                    currentline.remove();
                } else {
                    // Doing this earlier catches the starting mouse click by mistake
                    document.id(document).addEvent('mouseup', finishline);
                }

                switch (currenttool) {
                case 'rectangle':
                    w = Math.abs(ex - linestartpos.x);
                    h = Math.abs(ey - linestartpos.y);
                    sx = Math.min(linestartpos.x, ex);
                    sy = Math.min(linestartpos.y, ey);
                    currentline = currentpaper.rect(sx, sy, w, h);
                    break;
                case 'oval':
                    rx = Math.abs(ex - linestartpos.x) / 2;
                    ry = Math.abs(ey - linestartpos.y) / 2;
                    sx = Math.min(linestartpos.x, ex) + rx; // Add 'rx'/'ry' to get to the middle
                    sy = Math.min(linestartpos.y, ey) + ry;
                    currentline = currentpaper.ellipse(sx, sy, rx, ry);
                    break;
                case 'freehand':
                    dx = linestartpos.x - ex;
                    dy = linestartpos.y - ey;
                    dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 2) { // Trying to reduce the number of points a bit
                        return;
                    }
                    currentline = currentpaper.path("M " + linestartpos.x + " " + linestartpos.y + "L" + ex + " " + ey);
                    freehandpoints.push({x: ex, y: ey});
                    linestartpos.x = ex;
                    linestartpos.y = ey;
                    break;
                case 'highlight':
                    w = Math.abs(ex - linestartpos.x);
                    h = HIGHLIGHT_LINEWIDTH;
                    sx = Math.min(linestartpos.x, ex);
                    sy = linestartpos.y - 0.5 * HIGHLIGHT_LINEWIDTH;
                    currentline = currentpaper.rect(sx, sy, w, h);
                    break;
                case 'stamp':
                    w = Math.abs(ex - linestartpos.x);
                    h = Math.abs(ey - linestartpos.y);
                    sx = Math.min(linestartpos.x, ex);
                    sy = Math.min(linestartpos.y, ey);
                    currentline = currentpaper.image(getstampimage(getcurrentstamp()), sx, sy, w, h);
                    break;
                default: // Comment + Ctrl OR line
                    currentline = currentpaper.path("M " + linestartpos.x + " " + linestartpos.y + "L" + ex + " " + ey);
                    break;
                }
                if (currenttool === 'highlight') {
                    currentline.attr("stroke-width", 0);
                } else {
                    currentline.attr("stroke-width", LINEWIDTH);
                }
                setlinecolour(getcurrentlinecolour(), currentline, currenttool);
            }

            function startline(e) {
                if (!server.editing) {
                    return true;
                }
                if (e.rightClick) {
                    return true;
                }

                if (currentpaper) {
                    return true; // If user clicks very quickly this can happen
                }

                var tool, modifier, dims, sx, sy;
                tool = getcurrenttool();

                modifier = Browser.Platform.mac ? e.alt : e.control;
                if (tool === 'comment' && !modifier) {
                    return true;
                }
                if (tool === 'erase') {
                    return true;
                }

                if ($defined(currentcomment)) {
                    updatelastcomment();
                    return true;
                }

                context_quicklist.hide();
                context_comment.hide();

                e.preventDefault(); // Stop FF from dragging the image

                dims = document.id('pdfimg').getCoordinates();
                sx = e.page.x - dims.left;
                sy = e.page.y - dims.top;

                currentpaper = new Raphael(dims.left, dims.top, dims.width, dims.height);
                document.id(document).addEvent('mousemove', updateline);
                linestartpos = {x: sx, y: sy};
                if (tool === 'freehand') {
                    freehandpoints = [{x: linestartpos.x, y: linestartpos.y}];
                }
                if (tool === 'stamp') {
                    document.id(document).addEvent('mouseup', finishline); // Click without move = default sized stamp
                }

                return false;
            }

            function makeline(coords, type, id, colour, stamp) {
                var linewidth, halflinewidth, paper, line, details, boundary, container, i, maxx, maxy, minx, miny,
                    pathstr, temp, w, h, sx, sy, rx, ry, domcanvas;
                linewidth = LINEWIDTH;
                if (type === 'highlight') {
                    linewidth = 0;
                }
                halflinewidth = linewidth * 0.5;
                container = new Element('span');

                if (!$defined(colour)) {
                    colour = getcurrentlinecolour();
                }
                details = {type: type, colour: colour};

                if (type === 'freehand') {
                    details.path = coords[0].x + ',' + coords[0].y;
                    for (i = 1; i < coords.length; i += 1) {
                        details.path += ',' + coords[i].x + ',' + coords[i].y;
                    }

                    maxx = minx = coords[0].x;
                    maxy = miny = coords[0].y;
                    for (i = 1; i < coords.length; i += 1) {
                        minx = Math.min(minx, coords[i].x);
                        maxx = Math.max(maxx, coords[i].x);
                        miny = Math.min(miny, coords[i].y);
                        maxy = Math.max(maxy, coords[i].y);
                    }
                    boundary = {
                        x: (minx - (halflinewidth * 0.5)),
                        y: (miny - (halflinewidth * 0.5)),
                        w: (maxx + linewidth - minx),
                        h: (maxy + linewidth - miny)
                    };
                    if (boundary.h < 14) {
                        boundary.h = 14;
                    }
                    if (Browser.ie && Browser.version < 9) {
                        // Does not work with FF & Moodle
                        container.setStyles({
                            left: boundary.x,
                            top: boundary.y,
                            width: boundary.w + 2,
                            height: boundary.h + 2,
                            position: 'absolute'
                        });
                    } else {
                        // Does not work with IE
                        container.set('style', 'position:absolute; top:' + boundary.y + 'px; left:' + boundary.x + 'px; width:' + (boundary.w + 2) + 'px; height:' + (boundary.h + 2) + 'px;');
                    }
                    document.id('pdfholder').adopt(container);
                    paper = new Raphael(container);
                    minx -= halflinewidth;
                    miny -= halflinewidth;

                    pathstr = 'M' + (coords[0].x - minx) + ' ' + (coords[0].y - miny);
                    for (i = 1; i < coords.length; i += 1) {
                        pathstr += 'L' + (coords[i].x - minx) + ' ' + (coords[i].y - miny);
                    }
                    line = paper.path(pathstr);
                } else {
                    if (type === 'stamp') {
                        if (Math.abs(coords.sx - coords.ex) < 4 && Math.abs(coords.sy - coords.ey) < 4) {
                            coords.ex = coords.sx + 40;
                            coords.ey = coords.sy + 40;
                        }
                    }
                    details.coords = { sx: coords.sx, sy: coords.sy, ex: coords.ex, ey: coords.ey };

                    if (coords.sx > coords.ex) { // Always go left->right
                        temp = coords.sx;
                        coords.sx = coords.ex;
                        coords.ex = temp;
                        temp = coords.sy;
                        coords.sy = coords.ey;
                        coords.ey = temp;
                    }
                    if (type === 'highlight') {
                        coords.sy -= HIGHLIGHT_LINEWIDTH * 0.5;
                        coords.ey = coords.sy + HIGHLIGHT_LINEWIDTH;
                    }
                    if (coords.sy < coords.ey) {
                        boundary = {
                            x: (coords.sx - (halflinewidth * 0.5)),
                            y: (coords.sy - (halflinewidth * 0.5)),
                            w: (coords.ex + linewidth - coords.sx),
                            h: (coords.ey + linewidth - coords.sy)
                        };
                        coords.sy = halflinewidth;
                        coords.ey = boundary.h - halflinewidth;
                    } else {
                        boundary = {
                            x: (coords.sx - (halflinewidth * 0.5)),
                            y: (coords.ey - (halflinewidth * 0.5)),
                            w: (coords.ex + linewidth - coords.sx),
                            h: (coords.sy + linewidth - coords.ey)
                        };
                        coords.sy = boundary.h - halflinewidth;
                        coords.ey = halflinewidth;
                    }
                    coords.sx = halflinewidth;
                    coords.ex = boundary.w - halflinewidth;
                    if (boundary.h < 14) {
                        boundary.h = 14;
                    }
                    if (Browser.ie && Browser.version < 9) {
                        // Does not work with FF & Moodle
                        container.setStyles({
                            left: boundary.x,
                            top: boundary.y,
                            width: boundary.w + 2,
                            height: boundary.h + 2,
                            position: 'absolute'
                        });
                    } else {
                        // Does not work with IE
                        container.set('style', 'position:absolute; top:' + boundary.y + 'px; left:' + boundary.x + 'px; width:' + (boundary.w + 2) + 'px; height:' + (boundary.h + 2) + 'px;');
                    }
                    document.id('pdfholder').adopt(container);
                    paper = new Raphael(container);
                    switch (type) {
                    case 'rectangle':
                        w = Math.abs(coords.ex - coords.sx);
                        h = Math.abs(coords.ey - coords.sy);
                        sx = Math.min(coords.sx, coords.ex);
                        sy = Math.min(coords.sy, coords.ey);
                        line = paper.rect(sx, sy, w, h);
                        break;
                    case 'oval':
                        rx = Math.abs(coords.ex - coords.sx) / 2;
                        ry = Math.abs(coords.ey - coords.sy) / 2;
                        sx = Math.min(coords.sx, coords.ex) + rx;
                        sy = Math.min(coords.sy, coords.ey) + ry;
                        line = paper.ellipse(sx, sy, rx, ry);
                        break;
                    case 'highlight':
                        w = Math.abs(coords.ex - coords.sx);
                        h = HIGHLIGHT_LINEWIDTH;
                        sx = Math.min(coords.sx, coords.ex);
                        sy = Math.min(coords.sy, coords.ey);
                        line = paper.rect(sx, sy, w, h);
                        break;
                    case 'stamp':
                        w = Math.abs(coords.ex - coords.sx);
                        h = Math.abs(coords.ey - coords.sy);
                        sx = Math.min(coords.sx, coords.ex);
                        sy = Math.min(coords.sy, coords.ey);
                        if (!$defined(stamp)) {
                            stamp = getcurrentstamp();
                        }
                        line = paper.image(getstampimage(stamp), sx, sy, w, h);
                        details.path = stamp;
                        break;
                    default:
                        line = paper.path("M " + coords.sx + " " + coords.sy + " L " + coords.ex + " " + coords.ey);
                        details.type = 'line';
                        break;
                    }
                }
                line.attr("stroke-width", linewidth);
                setlinecolour(colour, line, type);

                domcanvas = document.id(paper.canvas);

                domcanvas.store('container', container);
                domcanvas.store('width', boundary.w);
                domcanvas.store('height', boundary.h);
                domcanvas.store('line', line);
                domcanvas.store('colour', colour);
                if (server.editing) {
                    domcanvas.addEvent('mousedown', startline);
                    domcanvas.addEvent('click', eraseline);
                    if ($defined(id)) {
                        domcanvas.store('id', id);
                    } else {
                        server.addannotation(details, domcanvas);
                    }
                } else {
                    domcanvas.store('id', id);
                }

                allannotations.push(container);
            }

            ServerComm = new Class({
                Implements: [Events],
                id: null,
                pageno: null,
                sesskey: null,
                url: null,
                retrycount: 0,
                editing: true,
                waitel: null,
                pageloadcount: 0,  // Store how many page loads there have been
                // (to allow ignoring of messages from server that arrive after page has changed)
                scrolltocommentid: 0,
                userid: null,

                initialize: function (settings) {
                    this.id = settings.id;
                    this.userid= settings.userid;
                    this.pageno = settings.pageno;
                    this.sesskey = settings.sesskey;
                    this.url = settings.updatepage;
                    this.editing = settings.editing.toInt();

                    this.waitel = new Element('div');
                    this.waitel.set('class', 'pagewait hidden');
                    document.id('pdfholder').adopt(this.waitel);
                },

                updatecomment: function (comment) {
                    if (!this.editing) {
                        return;
                    }
                    var waitel, pageloadcount, request;
                    waitel = new Element('div');
                    waitel.set('class', 'wait');
                    comment.adopt(waitel);
                    comment.store('oldcolour', comment.retrieve('colour'));
                    pageloadcount = this.pageloadcount;
                    request = new Request.JSON({
                        url: this.url,
                        timeout: resendtimeout,

                        onSuccess: function (resp) {
                            if (pageloadcount !== server.pageloadcount) { return; }
                            server.retrycount = 0;
                            if (waitel.destroy !== 'undefined') { waitel.destroy(); }

                            if (resp.error === 0) {
                                comment.store('id', resp.id);
                                // Re-attach drag and resize ability
                                comment.retrieve('drag').attach();
                                updatefindcomments(server.pageno.toInt(), resp.id, comment.retrieve('rawtext'));
                            } else {
                                if (confirm(server_config.lang_errormessage + resp.errmsg + '\n' + server_config.lang_okagain)) {
                                    server.updatecomment(comment);
                                } else {
                                    // Re-attach drag and resize ability
                                    comment.retrieve('drag').attach();
                                }
                            }
                        },

                        onFailure: function () {
                            if (pageloadcount !== server.pageloadcount) { return; }
                            if (waitel.destroy !== 'undefined') { waitel.destroy(); }
                            showsendfailed(function () { server.updatecomment(comment); });
                            // The following should really be on the 'cancel' (but probably unimportant)
                            comment.retrieve('drag').attach();
                        },

                        onTimeout: function () {
                            if (pageloadcount !== server.pageloadcount) { return; }
                            if (waitel.destroy !== 'undefined') { waitel.destroy(); }
                            showsendfailed(function () { server.updatecomment(comment); });
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

                removecomment: function (cid) {
                    if (!this.editing) {
                        return;
                    }
                    removefromfindcomments(cid);
                    var request = new Request.JSON({
                        url: this.url,
                        onSuccess: function (resp) {
                            server.retrycount = 0;
                            if (resp.error !== 0) {
                                if (confirm(server_config.lang_errormessage + resp.errmsg + '\n' + server_config.lang_okagain)) {
                                    server.removecomment(cid);
                                }
                            }
                        },
                        onFailure: function () {
                            showsendfailed(function () { server.removecomment(cid); });
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

                getcomments: function () {
                    this.waitel.removeClass('hidden');
                    var pageno, scrolltocommentid, request;
                    pageno = this.pageno;
                    scrolltocommentid = this.scrolltocommentid;
                    this.scrolltocommentid = 0;

                    request = new Request.JSON({
                        url: this.url,

                        onSuccess: function (resp) {
                            server.retrycount = 0;
                            server.waitel.addClass('hidden');
                            if (resp.error === 0) {
                                if (pageno === server.pageno) { // Make sure the page hasn't changed since we sent this request
                                    //document.id('pdfholder').getElements('div').destroy(); // Destroy all the currently displayed comments (just in case!) - this turned out to be a bad idea
                                    resp.comments.each(function (comment) {
                                        var cb, style;
                                        cb = makecommentbox(comment.position, comment.text, comment.colour);
                                        if (Browser.ie && Browser.version < 9) {
                                            // Does not work with FF & Moodle
                                            cb.setStyle('width', comment.width);
                                        } else {
                                            // Does not work with IE
                                            style = cb.get('style') + ' width:' + comment.width + 'px;';
                                            cb.set('style', style);
                                        }

                                        cb.store('id', comment.id);
                                    });

                                    // Get annotations at the same time
                                    allannotations.each(function (p) { p.destroy(); });
                                    allannotations.empty();
                                    resp.annotations.each(function (annotation) {
                                        var coords, points, i;
                                        if (annotation.type === 'freehand') {
                                            coords = [];
                                            points = annotation.path.split(',');
                                            for (i = 0; (i + 1) < points.length; i += 2) {
                                                coords.push({x: points[i].toInt(), y: points[i + 1].toInt()});
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
                                if (confirm(server_config.lang_errormessage + resp.errmsg + '\n' + server_config.lang_okagain)) {
                                    server.getcomments();
                                }
                            }
                        },

                        onFailure: function () {
                            showsendfailed(function () {server.getcomments(); });
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

                getquicklist: function () {
                    if (!this.editing) {
                        return;
                    }
                    var request = new Request.JSON({
                        url: this.url,

                        onSuccess: function (resp) {
                            server.retrycount = 0;
                            if (resp.error === 0) {
                                resp.quicklist.each(addtoquicklist);  // Assume contains: id, rawtext, colour, width
                            } else {
                                if (confirm(server_config.lang_errormessage + resp.errmsg + '\n' + server_config.lang_okagain)) {
                                    server.getquicklist();
                                }
                            }
                        },

                        onFailure: function () {
                            showsendfailed(function () { server.getquicklist(); });
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

                addtoquicklist: function (element) {
                    if (!this.editing) {
                        return;
                    }
                    var request = new Request.JSON({
                        url: this.url,

                        onSuccess: function (resp) {
                            server.retrycount = 0;
                            if (resp.error === 0) {
                                addtoquicklist(resp.item);  // Assume contains: id, rawtext, colour, width
                            } else {
                                if (confirm(server_config.lang_errormessage + resp.errmsg + '\n' + server_config.lang_okagain)) {
                                    server.addtoquicklist(element);
                                }
                            }
                        },

                        onFailure: function () {
                            showsendfailed(function () { server.addtoquicklist(element); });
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

                removefromquicklist: function (itemid) {
                    if (!this.editing) {
                        return;
                    }
                    var request = new Request.JSON({
                        url: this.url,
                        onSuccess: function (resp) {
                            server.retrycount = 0;
                            if (resp.error === 0) {
                                removefromquicklist(resp.itemid);
                            } else {
                                if (confirm(server_config.lang_errormessage + resp.errmsg + '\n' + server_config.lang_okagain)) {
                                    server.removefromquicklist(itemid);
                                }
                            }
                        },

                        onFailure: function () {
                            showsendfailed(function () { server.removefromquicklist(itemid); });
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

                getimageurl: function (pageno, changenow) {
                    if (changenow) {
                        if ($defined(pagelist[pageno])) {
                            showpage(pageno);
                            pagesremaining += 1;
                            if (pagesremaining > 1) {
                                return; // Already requests pending, so no need to send any more
                            }
                        } else {
                            waitingforpage = pageno;
                            pagesremaining = pagestopreload; // Wanted a page that wasn't preloaded, so load a few more
                            document.id('pdfimg').setProperty('src', server_config.blank_image);
                        }
                    }

                    var pagecount, startpage, request;

                    pagecount = server_config.pagecount.toInt();
                    if (pageno > pagecount) {
                        pageno = 1;
                    }
                    startpage = pageno;

                    // Find the next page that has not already been loaded
                    while ((pageno <= pagecount) && $defined(pagelist[pageno])) {
                        pageno += 1;
                    }
                    // Wrap around to the beginning again
                    if (pageno > pagecount) {
                        pageno = 1;
                        while ($defined(pagelist[pageno])) {
                            if (pageno === startpage) {
                                return; // All pages preloaded, so stop
                            }
                            pageno += 1;
                        }
                    }

                    request = new Request.JSON({
                        url: this.url,

                        onSuccess: function (resp) {
                            server.retrycount = 0;
                            if (resp.error === 0) {
                                pagesremaining -= 1;
                                pagelist[pageno] = {};
                                pagelist[pageno].url = resp.image.url;
                                pagelist[pageno].width = resp.image.width;
                                pagelist[pageno].height = resp.image.height;
                                pagelist[pageno].image = new Image(resp.image.width, resp.image.height);
                                pagelist[pageno].image.src = resp.image.url;
                                if (waitingforpage === pageno) {
                                    showpage(pageno);
                                    waitingforpage = -1;
                                }

                                if (pagesremaining > 0) {
                                    var nextpage = pageno.toInt() + 1;
                                    server.getimageurl(nextpage, false);
                                }

                            } else {
                                if (confirm(server_config.lang_errormessage + resp.errmsg + '\n' + server_config.lang_okagain)) {
                                    server.getimageurl(pageno, false);
                                }
                            }
                        },

                        onFailure: function () {
                            showsendfailed(function () { server.getimageurl(pageno, false); });
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

                addannotation: function (details, annotation) {
                    if (!this.editing) {
                        return;
                    }
                    this.waitel.removeClass('hidden');

                    if (!$defined(details.id)) {
                        details.id = -1;
                    }

                    var pageloadcount, request, requestdata;
                    pageloadcount = this.pageloadcount;

                    request = new Request.JSON({
                        url: this.url,

                        onSuccess: function (resp) {
                            if (pageloadcount !== server.pageloadcount) { return; }
                            server.retrycount = 0;
                            server.waitel.addClass('hidden');

                            if (resp.error === 0) {
                                if (details.id < 0) { // A new line
                                    annotation.store('id', resp.id);
                                }
                            } else {
                                if (confirm(server_config.lang_errormessage + resp.errmsg + '\n' + server_config.lang_okagain)) {
                                    server.addannotation(details, annotation);
                                }
                            }
                        },

                        onFailure: function () {
                            if (pageloadcount !== server.pageloadcount) { return; }
                            server.waitel.addClass('hidden');
                            showsendfailed(function () { server.addannotation(details, annotation); });
                        }

                    });

                    requestdata = {
                        action: 'addannotation',
                        annotation_colour: details.colour,
                        annotation_type: details.type,
                        annotation_id: details.id,
                        id: this.id,
                        userid: this.userid,
                        pageno: this.pageno,
                        sesskey: this.sesskey
                    };

                    if (details.type === 'freehand') {
                        requestdata.annotation_path = details.path;
                    } else {
                        requestdata.annotation_startx = details.coords.sx;
                        requestdata.annotation_starty = details.coords.sy;
                        requestdata.annotation_endx = details.coords.ex;
                        requestdata.annotation_endy = details.coords.ey;
                    }
                    if (details.type === 'stamp') {
                        requestdata.annotation_path = details.path;
                    }
                    request.send({ data: requestdata }); // Move this line down, once all working
                },

                removeannotation: function (aid) {
                    if (!this.editing) {
                        return;
                    }
                    var request = new Request.JSON({
                        url: this.url,
                        onSuccess: function (resp) {
                            server.retrycount = 0;
                            if (resp.error !== 0) {
                                if (confirm(server_config.lang_errormessage + resp.errmsg + '\n' + server_config.lang_okagain)) {
                                    server.removeannotation(aid);
                                }
                            }
                        },
                        onFailure: function () {
                            showsendfailed(function () { server.removeannotation(aid); });
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

                scrolltocomment: function (commentid) {
                    this.scrolltocommentid = commentid;
                }

            });

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

                if (getcurrenttool() !== 'comment') {
                    return;
                }

                var modifier, imgpos, offs;
                modifier = Browser.Platform.mac ? e.alt : e.control;
                if (!modifier) {  // If control pressed, then drawing line
                    // Calculate the relative position of the comment
                    imgpos = document.id('pdfimg').getPosition();
                    offs = {
                        x: e.page.x - imgpos.x,
                        y: e.page.y - imgpos.y
                    };
                    currentcomment = makecommentbox(offs);
                }
            }

            function eraseline(e) {
                if (!server.editing) {
                    return false;
                }
                if (getcurrenttool() !== 'erase') {
                    return false;
                }

                var id, container;
                id = this.retrieve('id');
                if (id) {
                    container = this.retrieve('container');
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

                /*var modifier = Browser.Platform.mac ? e.alt : e.control;*/

                if (e.key === 'n') {
                    gotonextpage();
                } else if (e.key === 'p') {
                    gotoprevpage();
                }
                if (server.editing) {
                    if (e.key === 'c') {
                        setcurrenttool('comment');
                    } else if (e.key === 'l') {
                        setcurrenttool('line');
                    } else if (e.key === 'r') {
                        setcurrenttool('rectangle');
                    } else if (e.key === 'o') {
                        setcurrenttool('oval');
                    } else if (e.key === 'f') {
                        setcurrenttool('freehand');
                    } else if (e.key === 'h') {
                        setcurrenttool('highlight');
                    } else if (e.key === 's') {
                        setcurrenttool('stamp');
                    } else if (e.key === 'e') {
                        setcurrenttool('erase');
                        /*} else if (e.key === 'g' && modifier) {
                         // get this working (at some point)
                         var btn = document.id('generateresponse');
                         var frm = btn.parentNode;
                         frm.submit();*/
                    } else if (e.code === 219) {  // { or [
                        if (e.shift) {
                            prevlinecolour();
                        } else {
                            prevcommentcolour();
                        }
                    } else if (e.code === 221) {  // } or ]
                        if (e.shift) {
                            nextlinecolour();
                        } else {
                            nextcommentcolour();
                        }
                    }
                }
            }

            function doscrolltocomment(commentid) {
                commentid = parseInt(commentid, 10);
                if (commentid === 0) {
                    return;
                }
                if (lasthighlight) {
                    lasthighlight.removeClass('comment-highlight');
                    lasthighlight = null;
                }
                var comments = document.id('pdfholder').getElements('.comment');
                comments.each(function (comment) {
                    var dims, win, scroll, view, scrolltocoord;

                    if (parseInt(comment.retrieve('id'), 10) === commentid) {
                        comment.addClass('comment-highlight');
                        lasthighlight = comment;

                        dims = comment.getCoordinates();
                        win = window.getCoordinates();
                        scroll = window.getScroll();
                        view = win;
                        view.right += scroll.x;
                        view.bottom += scroll.y;

                        scrolltocoord = {x: scroll.x, y: scroll.y};

                        if (view.right < (dims.right + 10)) {
                            if ((dims.width + 20) < win.width) {
                                // Scroll right of comment onto the screen (if it will all fit)
                                scrolltocoord.x = dims.right + 10 - win.width;
                            } else {
                                // Just scroll the left of the comment onto the screen
                                scrolltocoord.x = dims.left - 10;
                            }
                        }

                        if (view.bottom < (dims.bottom + 10)) {
                            if ((dims.height + 20) < win.height) {
                                // Scroll bottom of comment onto the screen (if it will all fit)
                                scrolltocoord.y = dims.bottom + 10 - win.height;
                            } else {
                                // Just scroll top of comment onto the screen
                                scrolltocoord.y = dims.top - 10;
                            }
                        }

                        window.scrollTo(scrolltocoord.x, scrolltocoord.y);
                    }
                });
            }

            function startjs() {
                new Asset.css('style/annotate.css');
                /*new Asset.css(server_config.css_path+'menu.css');
                new Asset.css(server_config.css_path+'button.css');*/
                server = new ServerComm(server_config);

                var showPreviousMenu, colour, linecolour, stamp, tool, pageno, sel, selidx, selpage, btn;

                if (server.editing) {
                    if (document.getElementById('choosecolour')) {
                        colourmenu = new YH.widget.Button("choosecolour", {
                            type: "menu",
                            menu: "choosecolourmenu",
                            lazyloadmenu: false
                        });
                        colourmenu.on("selectedMenuItemChange", function (e) {
                            var menuitem, colour;
                            menuitem = e.newValue;
                            colour = (/choosecolour-([a-z]*)/i.exec(menuitem.element.className))[1];
                            this.set("label", '<img src="' + server_config.image_path + colour + '.gif" />');
                            this.set("value", colour);
                            changecolour();
                        });
                    }
                    if (document.getElementById('chooselinecolour')) {
                        linecolourmenu = new YH.widget.Button("chooselinecolour", {
                            type: "menu",
                            menu: "chooselinecolourmenu",
                            lazyloadmenu: false
                        });
                        linecolourmenu.on("selectedMenuItemChange", function (e) {
                            var menuitem, colour;
                            menuitem = e.newValue;
                            colour = (/choosecolour-([a-z]*)/i.exec(menuitem.element.className))[1];
                            this.set("label", '<img src="' + server_config.image_path + 'line' + colour + '.gif" />');
                            this.set("value", colour);
                            changelinecolour();
                        });
                    }
                    if (document.getElementById('choosestamp')) {
                        stampmenu = new YH.widget.Button("choosestamp", {
                            type: "menu",
                            menu: "choosestampmenu",
                            lazyloadmenu: false
                        });
                        stampmenu.on("selectedMenuItemChange", function (e) {
                            var menuitem, stamp;
                            menuitem = e.newValue;
                            stamp = (/choosestamp-([a-z]*)/i.exec(menuitem.element.className))[1];
                            this.set("label", '<img width="32" height="32" src="' + getstampimage(stamp) + '" />');
                            this.set("value", stamp);
                            changestamp();
                        });
                    }
                    if (document.getElementById('showpreviousbutton')) {
                        showPreviousMenu = new YH.widget.Button("showpreviousbutton", {
                            type: "menu",
                            menu: "showpreviousselect",
                            lazyloadmenu: false
                        });
                        showPreviousMenu.on("selectedMenuItemChange", function (e) {
                            var compareid, url;
                            compareid = e.newValue.value;
                            url = 'editcomment.php?id=' + server.id + '&userid=' + server.userid + '&pageno=' + server.pageno;
                            if (compareid > -1) {
                                url += '&topframe=1&showprevious=' + compareid;
                            }
                            top.location = url;
                        });
                    }
                    if (document.getElementById('savedraft')) {
                        btn = new YH.widget.Button("savedraft");
                    }
                    if (document.getElementById('generateresponse')) {
                        btn = new YH.widget.Button("generateresponse");
                    }
                    if (document.getElementById('choosetoolgroup')) {
                        choosedrawingtool = new YH.widget.ButtonGroup("choosetoolgroup");
                        choosedrawingtool.on("checkedButtonChange", function (e) {
                            var newtool = e.newValue.get("value");
                            newtool = newtool.substr(0, newtool.length - 4); // Strip off the 'icon' part
                            Cookie.write('uploadpdf_tool', newtool);
                        });
                    }
                }
                btn = new YH.widget.Button("downloadpdf");
                prevbutton = new YH.widget.Button("prevpage");
                prevbutton.on("click", gotoprevpage);
                nextbutton = new YH.widget.Button("nextpage");
                nextbutton.on("click", gotonextpage);
                document.id('selectpage').addEvent('change', selectpage);
                document.id('selectpage2').addEvent('change', selectpage2);
                document.id('prevpage2').addEvent('click', gotoprevpage);
                document.id('nextpage2').addEvent('click', gotonextpage);
                findcommentsmenu = new YH.widget.Button("findcommentsbutton", {
                    type: "menu",
                    menu: "findcommentsselect",
                    lazyloadmenu: false
                });
                findcommentsmenu.on("selectedMenuItemChange", function (e) {
                    var menuval, pageno, commentid;
                    menuval = e.newValue.value;
                    pageno = menuval.split(':')[0].toInt();
                    commentid = menuval.split(':')[1].toInt();
                    if (pageno > 0) {
                        if (server.pageno.toInt() === pageno) {
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
                    document.id('pdfimg').ondragstart = function () { return false; }; // To stop ie trying to drag the image
                    colour = Cookie.read('uploadpdf_colour');
                    if (!$defined(colour)) {
                        colour = 'yellow';
                    }
                    setcurrentcolour(colour);
                    linecolour = Cookie.read('uploadpdf_linecolour');
                    if (!$defined(linecolour)) {
                        linecolour = 'red';
                    }
                    setcurrentlinecolour(linecolour);
                    stamp = Cookie.read('uploadpdf_stamp');
                    if (!$defined(stamp) || stamp === 'null') {
                        stamp = 'tick';
                    }
                    setcurrentstamp(stamp, false);
                    tool = Cookie.read('uploadpdf_tool');
                    if (!$defined(tool)) {
                        tool = 'comment';
                    }
                    setcurrenttool(tool);
                }

                // Start preloading pages if using js navigation method
                document.addEvent('keydown', keyboardnavigation);
                pagelist = [];
                pageno = server.pageno.toInt();
                // Little fix as Firefox remembers the selected option after a page refresh
                sel = document.id('selectpage');
                selidx = sel.selectedIndex;
                selpage = sel[selidx].value;
                if (parseInt(selpage, 10) !== pageno) {
                    gotopage(selpage);
                } else {
                    updatepagenavigation(pageno);
                    server.getimageurl(pageno + 1, false);
                }

                window.addEvent('beforeunload', function () {
                    pageunloading = true;
                });
            }

            function context_quicklistnoitems() {
                if (!server.editing) {
                    return;
                }

                if (context_quicklist.quickcount === 0) {
                    if (!context_quicklist.menu.getElement('a[href$=noitems]')) {
                        context_quicklist.addItem('noitems', server_config.lang_emptyquicklist + ' &#0133;', null, function () { alert(server_config.lang_emptyquicklist_instructions); });
                    }
                } else {
                    context_quicklist.removeItem('noitems');
                }
            }

            function addtoquicklist(item) {
                if (!server.editing) {
                    return;
                }
                var itemid, itemtext, itemfulltext;
                itemid = item.id;
                itemtext = item.text.trim().replace('\n', '');
                itemfulltext = false;
                if (itemtext.length > 30) {
                    itemtext = itemtext.substring(0, 30) + '&#0133;';
                    itemfulltext = item.text.trim().replace('<', '&lt;').replace('>', '&gt;');
                }
                itemtext = itemtext.replace('<', '&lt;').replace('>', '&gt;');


                quicklist[itemid] = item;

                context_quicklist.addItem(itemid, itemtext, server_config.deleteicon, function (id, menu) {
                    var imgpos, pos, cb, style;
                    imgpos = document.id('pdfimg').getPosition();
                    pos = {
                        x: menu.menu.getStyle('left').toInt() - imgpos.x - menu.options.offsets.x,
                        y: menu.menu.getStyle('top').toInt() - imgpos.y - menu.options.offsets.y
                    };
                    // Nasty hack to reposition the comment box in IE
                    if (Browser.ie && Browser.version < 9) {
                        if (Browser.ie6 || Browser.ie7) {
                            pos.x += 40;
                            pos.y -= 20;
                        } else {
                            pos.y -= 15;
                        }
                    }
                    cb = makecommentbox(pos, quicklist[id].text, quicklist[id].colour);
                    if (Browser.ie && Browser.version < 9) {
                        // Does not work with FF & Moodle
                        cb.setStyle('width', quicklist[id].width);
                    } else {
                        // Does not work with IE
                        style = cb.get('style') + ' width:' + quicklist[id].width + 'px;';
                        cb.set('style', style);
                    }
                    server.updatecomment(cb);
                }, itemfulltext);

                context_quicklist.quickcount += 1;
                context_quicklistnoitems();
            }

            function removefromquicklist(itemid) {
                if (!server.editing) {
                    return;
                }
                context_quicklist.removeItem(itemid);
                context_quicklist.quickcount -= 1;
                context_quicklistnoitems();
            }

            function initcontextmenu() {
                if (!server.editing) {
                    return;
                }
                var menu, items, n;
                document.body.grab(document.id('context-quicklist'));
                document.body.grab(document.id('context-comment'));

                //create a context menu
                context_quicklist = new ContextMenu({
                    targets: null,
                    menu: 'context-quicklist',
                    actions: {
                        removeitem: function (itemid) {
                            server.removefromquicklist(itemid);
                        }
                    }
                });
                context_quicklist.addmenu(document.id('pdfimg'));
                context_quicklist.quickcount = 0;
                context_quicklistnoitems();
                quicklist = [];

                if (Browser.ie6 || Browser.ie7) {
                    // Hack to draw the separator line correctly in IE7 and below
                    menu = document.getElementById('context-comment');
                    items = menu.getElementsByTagName('li');
                    for (n = 0; n < items.length; n += 1) {
                        if (items[n].className === 'separator') {
                            items[n].className = 'separatorie7';
                        }
                    }
                }

                context_comment = new ContextMenu({
                    targets: null,
                    menu: 'context-comment',
                    actions: {
                        addtoquicklist: function (element) {
                            server.addtoquicklist(element);
                        },
                        red: function (element) { updatecommentcolour('red', element); },
                        yellow: function (element) { updatecommentcolour('yellow', element); },
                        green: function (element) { updatecommentcolour('green', element); },
                        blue: function (element) { updatecommentcolour('blue', element); },
                        white: function (element) { updatecommentcolour('white', element); },
                        clear: function (element) { updatecommentcolour('clear', element); },
                        deletecomment: function (element) {
                            var id = element.retrieve('id');
                            if (id !== -1) {
                                server.removecomment(id);
                            }
                            element.destroy();
                        }
                    }
                });

                server.getquicklist();
            }

            function check_pageimage(pageno) {
                if (pageno !== server.pageno.toInt()) {
                    return; // Moved off the page in question
                }
                if (pagelist[pageno].image.complete) {
                    document.id('pdfimg').setProperty('src', pagelist[pageno].url);
                } else {
                    setTimeout(function () { check_pageimage(pageno); }, 200);
                }
            }

            startjs();
            initcontextmenu();
        });
}
