/**
 * Page behaviours
 *
 * This class adds various behaviours to the rendered page
 */
dw_bibtex = {
    /**
     * initialize page behaviours
     */
    init: function(){
        jQuery('a.bibtex_citekey').mouseover(dw_bibtex.bibtexReferenceDisplay);
    },

    /**
     * Create/get a insitu popup used by the footnotes
     *
     * @param target - the DOM element at which the popup should be aligned at
     * @param popup_id - the ID of the (new) DOM popup
     * @return the Popup jQuery object
     */
    insituPopup: function(target, popup_id) {
        // get or create the popup div
        var $bibtexdiv = jQuery('#' + popup_id);

        // popup doesn't exist, yet -> create it
        if($bibtexdiv.length === 0){
            $bibtexdiv = jQuery(document.createElement('div'))
                .attr('id', popup_id)
                .addClass('insitu-bibtexref JSbibtexref')
                .mouseleave(function () {jQuery(this).hide();});
            jQuery('.dokuwiki:first').append($bibtexdiv);
        }

        // position() does not support hidden elements
        $bibtexdiv.show().position({
            my: 'left top',
            at: 'left center',
            of: target
        }).hide();

        return $bibtexdiv;
    },

    /**
     * Display an insitu bibtex reference popup
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Chris Smith <chris@jalakai.co.uk>
     * @author Till Biskup <till@till-biskup.de>
     */
    bibtexReferenceDisplay: function () {
        var content = jQuery(jQuery(this)).html();

        if (content === null){
            return;
        }

        // strip the leading content anchors and their comma separators
        content = content.replace(/(^.*<span>)+\s*/gi, '');

        // prefix ids on any elements with "insitu__" to ensure they remain unique
        content = content.replace(/\bid=(['"])([^"']+)\1/gi,'id="insitu__$2');

        // now put the content into the wrapper
        dw_bibtex.insituPopup(this, 'insitu__bibtex').html(content).delay(600).show();
    }
};

jQuery(dw_bibtex.init);
