import React from 'react'
import { render } from 'react-dom'
import TagsSelect from '../components/TagsSelect'
import { addressMapper } from '../widgets/addressForm'

var tagsEl = document.querySelector('#store_tags');

if (tagsEl) {

  const el = document.createElement('div')
  tagsEl.closest('.form-group').appendChild(el)

  tagsEl.classList.add('d-none')

  const tags = JSON.parse(tagsEl.dataset.tags)
  const defaultValue = tagsEl.value

  render(
    <TagsSelect
      tags={ tags }
      defaultValue={ defaultValue }
      onChange={ tags => {
        const slugs = tags.map(tag => tag.slug)
        tagsEl.value = slugs.join(' ')
      } } />, el)
}

$('#address-form-modal').on('show.bs.modal', function (event) {
  var modal = $(this)
  var button = $(event.relatedTarget) // Button that triggered the modal
  var address = button.data('address')
  var newAddress = button.data('newAddress')
  var addressObj = button.data('addressObj')

  if (address && addressObj) {

    var streetAddress = button.data('streetAddress')
    var name = button.data('name')
    var description = button.data('description')

    modal.find('form input[type="search"]').val(streetAddress)

    // Map form fields
    addressMapper(modal.find('form input[type="search"]').get(0), addressObj)

    modal.find('form input[type="text"]').val(name)
    modal.find('form textarea').val(description)

    modal.find('form').attr('action', address)
  } else {
    modal.find('form input[type="search"]').val('')
    modal.find('form input[type="text"]').val('')

    modal.find('form').attr('action', newAddress)
  }
})
