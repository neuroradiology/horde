/**
 * jQuery Mobile UI Turba application logic.
 *
 * Copyright 2012 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you did
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author Michael Slusarz <slusarz@horde.org>
 */
var TurbaMobile = {

    /**
     * Event handler for the pagebeforechange event that implements loading of
     * deep-linked pages.
     *
     * @param object e     Event object.
     * @param object data  Event data.
     */
    toPage: function(e, data)
    {
        var url = HordeMobile.parseUrl(data.toPage);

        switch (url.view) {
        case 'entry':
            $.mobile.showPageLoadingMsg();
            $('#turba-entry-dl').hide();
            $.mobile.changePage($('#entry'), data.options);
            HordeMobile.doAction(
                'smartmobileEntry',
                {
                    key: url.params.key,
                    source: url.params.source
                },
                TurbaMobile.messageLoaded
            );
            e.preventDefault();
            break;
        }
    },

    /**
     * Callback method after the message has been loaded.
     *
     * @param object r  The Ajax response object.
     */
    messageLoaded: function(r)
    {
        $.mobile.hidePageLoadingMsg();

        if (r.error) {
            $.mobile.changePage($('#browse'));
            return;
        }

        if (r.name) {
            $('#turba-entry-name-block').show();
            $('#turba-entry-name').text(r.name);
        } else {
            $('#turba-entry-name-block').hide();
        }

        if (r.email) {
            $('#turba-entry-email-block').show();
            if (r.email_link) {
                $('#turba-entry-email').hide();
                $('#turba-entry-email-list').html(
                    '<li><a data-ajax="false" href=' + r.email_link + '>' + r.email + '</a></li>'
                ).listview('refresh');
            } else {
                $('#turba-entry-email').text(r.email).show();
                $('#turba-entry-email-list').html('').listview('refresh');
            }
        } else {
            $('#turba-entry-email-block').hide();
        }

        $('#turba-entry-dl').show();
    },

    /**
     * Catch-all event handler for the click event.
     *
     * @param object e  An event object.
     */
    clickHandler: function(e)
    {
        var elt = $(e.target), id;

        while (elt && elt != window.document && elt.parent().length) {
            id = elt.attr('id');

            switch (id) {
            case 'turba-browse-top':
            case 'turba-entry-top':
                $.mobile.silentScroll();
                elt.blur();
                return;
            }

            elt = elt.parent();
        }
    },

    /**
     * Event handler for the document-ready event, responsible for the initial
     * setup.
     */
    onDocumentReady: function()
    {
        // Set up HordeMobile.
        $(document).bind('vclick', TurbaMobile.clickHandler);
        $(document).bind('pagebeforechange', TurbaMobile.toPage);
    }

};

// JQuery Mobile setup
$(TurbaMobile.onDocumentReady);
