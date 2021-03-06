(function(deck_selection, $) {

    var Decks = {};
    var HeroConflicts = false;

    deck_selection.init = function(decks) {
        Decks = decks;
    };

    deck_selection.cols = 1;
    deck_selection.headerOnly = true;
    deck_selection.options = {};

    var modal_deck_number;
    deck_selection.init_buttons = function() {
        $('#deck-selection, #deckSelectionModal').on('click', 'a[data-action], label[data-action]', function() {
            var btn = $(this);
            var action = btn.data('action');
            if (!action) {
                return;
            }

            switch (action) {
                case 'show-cards':
                case 'hide-cards':
                    btn.addClass('hidden').siblings('a').removeClass('hidden');
                    deck_selection.headerOnly = !deck_selection.headerOnly;
                    deck_selection.refresh_deck();
                    break;

                case 'remove-deck':
                    var deck_number = btn.data('deck');
                    Decks[deck_number] = null;
                    deck_selection.activate_deck(deck_number);
                    deck_selection.display_deck();
                    break;

                case 'select-deck':
                    var modal = $('#deckSelectionModal').modal('show');
                    modal_deck_number = btn.data('deck');

                    modal.find('label[data-action="my-decks"]').trigger('click');
                    break;

                case 'my-decks':
                    if (deck_list_xhr) {
                        deck_list_xhr.abort();
                    }

                    deck_selection.load_deck_list(modal_deck_number);
                    $('#deckSelectionAnotherPlayerBox').addClass('hidden');
                    btn.addClass('active').siblings().removeClass('active');
                    break;

                case 'other-decks':
                    if (deck_list_xhr) {
                        deck_list_xhr.abort();
                    }

                    $('#deckSelectionAnotherPlayerBox').removeClass('hidden');
                    btn.addClass('active').siblings().removeClass('active');
                    $('#deckSelectionList').empty();
                    break;
            }
        });
    };

    // Unfortunately, to allow many decks on a single screen at once would require big changes to app.deck and app.data.
    // We will activate each deck at once to ease this change.
    var selectedDeck = null;
    deck_selection.activate_deck = function(ix) {
        var deck = Decks[ix];
        selectedDeck = ix;
        if (deck) {
            app.deck.init(deck);
        }
    };

    deck_selection.display_deck = function() {
        if (Decks[selectedDeck]) {
            var cols = Math.min(deck_selection.cols, deck_selection.options.maxcols || 100);
            var sort = deck_selection.options.sort || 'type';

            app.deck.display('#deck' + selectedDeck + '-content', { cols: cols, sort: sort, special_meta: true, header_only: deck_selection.headerOnly }, false);
            $('.selected-deck-placeholder').eq(selectedDeck - 1).addClass('hidden');
            $('.selected-deck-content').eq(selectedDeck - 1).removeClass('hidden');
            $('input[name="deck' + selectedDeck + '_id"]').val(Decks[selectedDeck].id);
            $('input[name="deck' + selectedDeck + '_is_decklist"]').val(Decks[selectedDeck].is_published);
        } else {
            $('#deck' + selectedDeck + '-content').empty();
            $('.selected-deck-placeholder').eq(selectedDeck - 1).removeClass('hidden');
            $('.selected-deck-content').eq(selectedDeck - 1).addClass('hidden');
            $('input[name="deck' + selectedDeck + '_id"]').val('');
            $('input[name="deck' + selectedDeck + '_is_decklist"]').val(false);
        }

        deck_selection.show_conflicts();
        $(document).trigger('deck-changed');
    };

    deck_selection.check_problems = function(e) {
        if (HeroConflicts) {
            e.preventDefault();
            alert("You are not able to save a fellowship with conflicting heroes.");
        }
    };

    deck_selection.show_conflicts = function() {
        if (deck_selection.disable_conflict) {
            return;
        }

        HeroConflicts = false;
        $('.conflicted-hero').removeClass('conflicted-hero');
        $('.limited-pool-conflict').removeClass('limited-pool-conflict').removeAttr('title');
        $('.card-conflict, .problem').remove();

        var cores = 1;
        if (app.user.data && app.user.data.owned_packs) {
            if (app.user.data.owned_packs.match(/1-2/)) {
                cores++;
            }

            if (app.user.data.owned_packs.match(/1-3/)) {
                cores++;
            }
        } else {
            cores = 3;
        }

        var cardCount = {};
        var cardNames = {};
        var cards;
        for (var i = 1; i <= 4; i++) {
            if (!Decks[i]) {
                continue;
            }

            app.deck.init(Decks[i]);
            cards = app.deck.get_cards();

            cards.forEach(function(card) {
                if (!cardCount[card.code]) {
                    cardCount[card.code] = {
                        total: 0,
                        decks: 0,
                        card: card
                    };
                }

                if (!cardNames[card.s_name]) {
                    cardNames[card.s_name] = {
                        total: 0,
                        decks: 0,
                        heroes: 0
                    }
                }

                cardCount[card.code].total += card.indeck;
                cardCount[card.code].decks++;

                cardNames[card.s_name].total += card.indeck;
                cardNames[card.s_name].decks++;
                if (card.type_code == 'hero') {
                    cardNames[card.s_name].heroes++;
                }
            });
        }

        _.each(cardCount, function(record) {
            var card = record.card;

            // Adding ban symbol to repeated unique cards
            if (card.is_unique && (record.decks > 1 || cardNames[card.s_name].decks > 1)) {
                var div = $('.card[data-code="' + card.code + '"]');

                if (card.type_code == 'hero') {
                    if (cardNames[card.s_name].heroes > 1) {
                        HeroConflicts = true;
                        div.parent().addClass('conflicted-hero');

                        var content = div.closest('.deck-content');
                        if (content.find('.problem').size() == 0) {
                            $('<div class="text-danger problem"><span class="fa fa-exclamation-triangle"></span> Hero conflicts between selected decks</div>').insertAfter(content.find('.deckcardcount'));
                        }
                    }
                } else {
                    if (cardNames[card.s_name].heroes) {
                        div.after('&#160;<i class="fa fa-ban card-conflict text-danger" title="This unique card conflicts with a hero being used in a selected deck."></i>');
                    } else {
                        var leaves_play = (
                            (card.s_name == 'gandalf' && card.pack_code == 'Core') ||
                            (card.s_name == 'galadriel' && card.pack_code == 'TRD') ||
                            (card.s_name == 'elrond' && card.pack_code == 'TRD') ||
                            (card.s_name == 'saruman' && card.pack_code == 'VoI')
                        );

                        if (leaves_play) {
                            div.after('&#160;<i class="fa fa-ban card-conflict text-faded" title="This unique card is being used in more than one selected deck, but it\'s effect includes leaving play at the end of the round."></i>');
                        } else {
                            div.after('&#160;<i class="fa fa-ban card-conflict text-danger" title="This unique card is being used in more than one selected deck."></i>');
                        }
                    }
                }
            }

            var max_qty = card.quantity;

            if (card.pack_code == 'Core') {
                max_qty = card.quantity * cores;
            }

            if (card.owned && record.total > max_qty) {
                var span = $('.card[data-code="' + card.code + '"]').siblings('.card-count');

                span.addClass('limited-pool-conflict')
                    .attr('title', 'A total of ' + record.total + ' copies of this card are being used between selected decks but you only have ' + max_qty + (max_qty == 1 ? ' copy' : ' copies') + ' in your collection.');
            }
        });
    };

    deck_selection.refresh_deck = function(options) {
        deck_selection.options = options || deck_selection.options;
        deck_selection.disable_conflict = true;
        for (var i = 1; i <= 4; i++) {
            deck_selection.activate_deck(i);
            deck_selection.display_deck();
        }
        deck_selection.disable_conflict = false;
        deck_selection.show_conflicts();
    };

    deck_selection.display_deck_selection_list = function(deck_number, decks) {
        var tbody = $('#deckSelectionList').empty();
        var tr;
        var td;

        if (!decks || decks.success === false) {
            $('<tr />')
                .append($('<td />').text(decks.error || 'No decks found.'))
                .appendTo(tbody);

            return;
        }

        _.each(decks, function(deck) {
            if (deck.problem) {
                return;
            }

            var disabled = false;
            for (var i = 1; i <= 4; i++) {
                if (Decks[i] && Decks[i].id == deck.id && Decks[i].is_published == deck.is_published) {
                    disabled = true;
                }
            }

            tr = $('<tr />').data('id', deck.id).appendTo(tbody);
            td = $('<td class="decklist-hero-image hidden-xs"/>').appendTo(tr);

            _.each(deck.heroes, function(count, hero) {
                var heroCard = app.data.cards.findById(hero);

                if (!heroCard) {
                    return;
                }
                $('<div class="decklist-hero"></div>')
                    .addClass('border-light-' + heroCard.sphere_code)
                    .append('<div class="hero-thumbnail card-thumbnail-4x card-thumbnail-hero" style="background-image:url(\'/bundles/cards/' + heroCard.code + '.png\')"></div>')
                    .appendTo(td);

                td.append(' ');
            });

            td = $('<td />').appendTo(tr);
            td.text(deck.name + ' ' + deck.version + ' ');

            if (deck.is_published) {
                var social = [];
                social.push('<span class="social-icons">');
                social.push('<span class="social-icon-like"><span class="fa fa-heart"></span> <span class="num">' + deck.nb_votes + '</span></span>');
                social.push('<span class="social-icon-favorite"><span class="fa fa-star"></span> <span class="num">' + deck.nb_favorites + '</span></span>');
                social.push('<span class="social-icon-comment"><span class="fa fa-comment"></span> <span class="num">' + deck.nb_comments + '</span></span>');
                social.push('</span>');

                $('<div class="deck-info" />').append(social.join('')).appendTo(td);
            } else {
                $('<div class="tags"><span class="tag">Unpublished.</span></div>').appendTo(td);
            }

            td = $('<td class="deck-selection-actions text-right" />').appendTo(tr);
            if (disabled) {
                $('<label class="btn btn-xs btn-default disabled">Selected</label>').appendTo(td);
            } else {
                var button = $('<a href="" class="btn btn-xs btn-default" title="Select this Deck"><span class="fa fa-check fa-fw text-success"></span></a>').appendTo(td);
                button.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    Decks[deck_number] = deck;
                    $('#deckSelectionModal').modal('hide');
                    deck_selection.activate_deck(deck_number);
                    deck_selection.display_deck();
                });
            }
        });
    };

    deck_selection.submit_search_user_decks = function(e) {
        e.stopPropagation();
        e.preventDefault();

        var username_or_url = $('#deckSelectionById').val();

        if (!username_or_url) {
            return;
        }

        var match = username_or_url.match(/\/(deck|decklist)\/view\/(\d+)/);
        if (match) {
            deck_selection.load_deck(modal_deck_number, match[2], match[1] == 'decklist');
        } else {
            deck_selection.load_user_deck_list(modal_deck_number, username_or_url);
        }
    };


    var deck_list_xhr = null;
    deck_selection.load_deck_list = function(deck_number) {
        $('#deckSelectionList').empty().append('<div class="deck-loading"><i class="fa fa-spinner fa-spin fa-5x"></i></div>');

        if (deck_list_xhr) {
            deck_list_xhr.abort();
        }

        deck_list_xhr = $.ajax(Routing.generate('api_private_my_decks'), {
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                deck_selection.display_deck_selection_list(deck_number, data);
            }
        });
    };

    deck_selection.load_user_deck_list = function(deck_number, username) {
        $('#deckSelectionList').empty().append('<div class="deck-loading"><i class="fa fa-spinner fa-spin fa-5x"></i></div>');

        if (deck_list_xhr) {
            deck_list_xhr.abort();
        }

        deck_list_xhr = $.ajax(Routing.generate('api_private_user_decks', { username: encodeURIComponent(username) }), {
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                deck_selection.display_deck_selection_list(deck_number, data);
            }
        });
    };

    deck_selection.load_deck = function(deck_number, deck_id, is_decklist) {
        $('#deckSelectionList').empty().append('<div class="deck-loading"><i class="fa fa-spinner fa-spin fa-5x"></i></div>');

        if (deck_list_xhr) {
            deck_list_xhr.abort();
        }

        var route = is_decklist ? Routing.generate('api_decklist', { decklist_id: deck_id  }) : Routing.generate('api_private_load_deck', { id: deck_id });

        deck_list_xhr = $.ajax(route, {
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success === false) {
                    deck_selection.display_deck_selection_list(deck_number, data);
                } else {
                    deck_selection.display_deck_selection_list(deck_number, [data]);
                }
            }
        });
    };
})(app.deck_selection = {}, jQuery);
