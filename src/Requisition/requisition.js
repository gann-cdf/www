import urlsTemplate from "./requisition-urls.template.html";
import detailTemplate from "./requisition-detail.template.html";
import itemTemplate from "./item.template.html";
import spinner from "./spinner.template.html";
import "./requisition.css";

const root = document.querySelector('#root');
let
  form,
  teams = [],
  items = [],
  team;

const onChangeOrAutofill = (elt, handler) => {
  // such a hack -- https://github.com/vuejs/vue/issues/7058#issuecomment-441322358
  elt.onchange = handler;
  elt.addEventListener('animationstart', handler);
}

/****************************************************************************
 * Requsition URLS
 */

let teamField, urlsField;

const populateTeamsSelector = () => {
  teams = JSON.parse(process.env.TEAMS);
  teamField = document.querySelector('#team');
  teams.forEach(t => {
    const option = document.createElement('option');
    teamField.appendChild(option);
    option.outerHTML = `<option value="${t.number}">Team ${t.number} &ldquo;${t.name}&rdquo;</option>`;
  })
  teamField.onchange = handleTeamChange;
  if (team) {
    teamField.value = team;
    handleTeamChange();
  }
}

const handleTeamChange = () => {
  let teamName = 'Team';
  validateTeam();
  teams.forEach(team => {
    if (teamField.value === team.number) {
      teamName = team.name;
    }
  })
  document.querySelectorAll('.team-name').forEach(instance => instance.innerText = teamName);
}

const validateTeam = () => {
  if (teamField && teamField.value.length > 0) {
    teamField.setCustomValidity('');
    return true;
  } else {
    teamField.setCustomValidity('error');
    return false;
  }
}

const handleUrlsChange = () => {
  validateUrls();
}

const validateUrls = () => {
  if (urlsField && urlsField.value && /^(\n|\s*(\d+\s+)?https?:\/\/.*\n?)+$/.test(urlsField.value)) {
    urlsField.setCustomValidity('');
    return true;
  } else {
    urlsField.setCustomValidity('error');
    return false;
  }
}

const handleUrlsSubmission = async event => {
  event.preventDefault();
  const form = root.querySelector('form#requisition-urls');
  if (false === (form.checkValidity() && validateTeam() && validateUrls())) {
    event.stopPropagation();
  } else {
    const data = {};
    for (let i = 0; i < form.elements.length; i++) {
      const elt = form.elements.item(i);
      if (elt.name) {
        data[elt.name] = elt.value
      }
    }
    root.innerHTML = spinner;
    const response = await (await fetch('forms.php', {
      method: 'POST',
      body: JSON.stringify(data)
    })).json();
    team = response.team;
    items = response.items;
    initDetailForm();
  }
  form.classList.add('was-validated');
}

const initUrlsForm = () => {
  root.innerHTML = urlsTemplate;
  form = root.querySelector('form#requisition-urls');
  urlsField = form.querySelector('#urls');
  urlsField.onchange = handleUrlsChange;
  form.onsubmit = handleUrlsSubmission;
  form.querySelector('button[type="submit"]').onclick = handleUrlsSubmission;
  populateTeamsSelector();
}

/****************************************************************************
 * Requisition Details
 */

let itemsGroup;

const populateItems = () => {
  if (form) {
    itemsGroup = form.querySelector('#items');
    if (itemsGroup) {
      items.forEach((item, i) => {
        let itemHtml = itemTemplate;
        item.index = i;
        Object.keys(item).forEach(prop => {
          itemHtml = itemHtml.replace(new RegExp(`%%${prop}%%`, 'ig'), `${String(item[prop]).replace(/ /g, '&#32;')}`);
        })
        itemHtml = itemHtml.replace(/%%w[^%]+%%/g, '');
        let itemBlock = document.createElement('div');
        itemsGroup.appendChild(itemBlock);
        itemBlock.outerHTML = itemHtml;

        itemBlock = itemsGroup.querySelector(`#item-${i}`);

        onChangeOrAutofill(itemBlock.querySelector('.quantity'), handleItemTotalChange.bind(null, itemBlock));
        onChangeOrAutofill(itemBlock.querySelector('.unit-cost'), handleItemTotalChange.bind(null, itemBlock));
        onChangeOrAutofill(itemBlock.querySelector('.name'), handleNameChange.bind(null, itemBlock));

        itemBlock.querySelector('.close').onclick = handleRemoveItem.bind(null, itemBlock);

        const
          noteRow = itemBlock.querySelector('.note-row'),
          removeNote = itemBlock.querySelector('.remove-note'),
          addNote = itemBlock.querySelector('.add-note');
        itemBlock.querySelector('.add-note').onclick = () => {
          noteRow.classList.remove('d-none');
          removeNote.classList.remove('d-none');
          addNote.classList.add('d-none');
        };
        itemBlock.querySelector('.remove-note').onclick = () => {
          noteRow.classList.add('d-none');
          noteRow.querySelector('.note').value = null;
          removeNote.classList.add('d-none');
          addNote.classList.remove('d-none');
        };

        handleItemTotalChange(itemBlock);
        handleNameChange(itemBlock);
      })
    }
    refreshRemoveItemButtons();
  }

}

const handleNameChange = item => {
  let itemName = 'Item';
  const name = item.querySelector('.name').value;
  if (name) {
    itemName = name;
  }
  item.querySelector('.item-description').textContent = itemName;
}

const handleItemTotalChange = item => {
  let itemTotal = "";
  const quantity = item.querySelector('.quantity').value;
  const unitCost = item.querySelector('.unit-cost').value;
  if (quantity && unitCost) {
    itemTotal = `$${(quantity * unitCost).toFixed(2)}`;
  }
  item.querySelector('.item-total').textContent = itemTotal;
}

const refreshRemoveItemButtons = () => {
  const
    buttons = itemsGroup.querySelectorAll('button.close'),
    hide = buttons.length <= 1;
  buttons.forEach(button => {
    if (hide) {
      button.classList.add('d-none');
    } else {
      button.classList.remove('d-none');
    }
  });
}

const handleRemoveItem = item => {
  item.remove();
  refreshRemoveItemButtons()
}

const handleDetailSubmission = event => {
  event.preventDefault();
  const form = root.querySelector('form#requisition-detail');
  if (false === (form.checkValidity() && validateTeam())) {
    event.stopPropagation();
  } else {

  }
  form.classList.add('was-validated');
}

const initDetailForm = () => {
  root.innerHTML = detailTemplate;
  form = root.querySelector('form#requisition-detail');
  populateTeamsSelector();
  populateItems();
  form.onsubmit = handleDetailSubmission;
  form.querySelector('button#submit').onclick = handleDetailSubmission;
  form.querySelector('button#reset').onclick = initUrlsForm;
}

/****************************************************************************
 * Init
 */

initUrlsForm();
