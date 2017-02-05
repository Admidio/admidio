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
 */
function Chat() {
    this.inputId       = "";
    this.chatId        = "";
    this.intervalId    = null;
    this.state         = 0;
    this.isUpdateingse = false;

    /**
     * Init Chat
     * @param {string} inputId
     * @param {string} chatId
     */
    this.init = function(inputId, chatId) {
        var self = this;

        this.inputId = inputId;
        this.chatId  = chatId;

        // Start update interval
        this.intervalId = setInterval(this.update, 2500);

        // watch textarea for release of key press [enter]
        $(this.inputId).keyup(function(e) {
            if (e.keyCode === 13) {
                var text = $(this).val().trim();
                if (text.length > 0) {
                    self.send(text);
                }
                $(this).val("");
            }
        });
    };

    /**
     * Update the chat
     * @param {function} [callback] Optional callback function that is executed after the update
     */
    this.update = function(callback) {
        var self = this;

        if (!this.isUpdateing) {
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
                        data.text.forEach(function(text) {
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
        }
    };

    /**
     * Send the message
     * @param {string} message
     */
    this.send = function(message) {
        var self = this;

        this.update(function() {
            $.post({
                url: "process.php",
                data: {
                    "function": "send",
                    "message": message,
                    "state": this.state
                },
                dataType: "json",
                success: function() {
                    self.update();
                }
            });
        });
    };
}
