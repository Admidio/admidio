/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Name: Admidio Chat Engine
 */

/**
 * Creates a Chat instance
 * @constructor
 * @param {string} inputId
 * @param {string} chatId
 */
function Chat(inputId, chatId) {
    this.inputId     = inputId;
    this.chatId      = chatId;
    this.intervalId  = null;
    this.state       = 0;
    this.isUpdateing = false;

    this.init();
}

/**
 * Init Chat
 */
Chat.prototype.init = function() {
    var self = this;

    // Start update interval
    this.intervalId = setInterval(this.update.bind(this), 2500);

    // watch textarea for release of key press [enter]
    $(this.inputId).keyup(function(e) {
        if (e.keyCode === 13) {
            var text = $(this).val().trim();
            if (text.length > 0) {
                self.send.call(self, text);
            }
            $(this).val("");
        }
    });
};

/**
 * Update the chat
 * @param {function} [callback] Optional callback function that is executed after the update
 */
Chat.prototype.update = function(callback) {
    var self = this;

    if (this.isUpdateing) {
        return;
    }

    this.isUpdateing = true;
    $.post({
        url: "process.php",
        data: {
            "function": "update",
            "state": this.state
        },
        dataType: "json",
        success: function(data) {
            if (data.text) {
                var chatArea = $(self.chatId);
                data.text.forEach(function (text) {
                    chatArea.append($("<p>" + text + "</p>"));
                });
                chatArea.scrollTop(chatArea[0].scrollHeight);
            }
            self.isUpdateing = false;
            self.state = data.state;

            if (callback && typeof callback === "function") {
                callback();
            }
        }
    });
};

/**
 * Send the message
 * @param {string} message
 */
Chat.prototype.send = function(message) {
    var self = this;

    this.update(function () {
        $.post({
            url: "process.php",
            data: {
                "function": "send",
                "message": message,
                "state": this.state
            },
            dataType: "json",
            success: function() {
                self.update.call(self);
            }
        });
    });
};
