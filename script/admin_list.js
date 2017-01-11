/**
 * Created by NicolasGERARD on 1/11/2017.
 * Javascript used in the admin/list.php plugin
 *
 * Just to be able to check or uncheck all the pages to move at once
 */

move_list = {

    checkBoxPages: document.querySelectorAll("input.checkbox-page"),
    checkAll: function () {

        this.checkBoxPages.forEach(function (checkBox) {
                checkBox.checked = true;
            }
        );

    },
    unCheckAll: function () {

        this.checkBoxPages.forEach(function (checkBox) {
                checkBox.checked = false;
            }
        );

    }

};

// Add the event handler
window.addEventListener("load",
    function () {
        document.querySelector("input.checkbox-manager").onclick =
            function (event) {
                if (event.target.checked) {
                    move_list.checkAll();
                } else {
                    move_list.unCheckAll();
                }
            }
    }
);
