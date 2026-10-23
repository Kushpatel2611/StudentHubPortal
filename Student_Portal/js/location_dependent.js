import { fetchJson } from './modules/api.js';

const countrySelect = document.getElementById('country');
const stateSelect = document.getElementById('state');
const citySelect = document.getElementById('city');

let locationData = [];

function resetSelect(selectElement, placeholderText) {
    selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
    selectElement.disabled = true;
}

function populateCountryDropdown() {
    if (!countrySelect) {
        return;
    }

    countrySelect.innerHTML = '<option value="">Select a country</option>';
    locationData.forEach((country) => {
        const option = document.createElement('option');
        option.value = country.country;
        option.textContent = country.country;
        countrySelect.appendChild(option);
    });
}

function populateStateDropdown(countryName) {
    const selectedCountry = locationData.find((item) => item.country === countryName);
    resetSelect(stateSelect, 'Select a state');
    resetSelect(citySelect, 'Select a city');

    if (!selectedCountry) {
        return;
    }

    stateSelect.disabled = false;
    selectedCountry.states.forEach((stateItem) => {
        const option = document.createElement('option');
        option.value = stateItem.state;
        option.textContent = stateItem.state;
        stateSelect.appendChild(option);
    });
}

function populateCityDropdown(stateName) {
    const selectedCountry = locationData.find((item) => item.country === countrySelect.value);
    const selectedState = selectedCountry ? selectedCountry.states.find((item) => item.state === stateName) : null;
    resetSelect(citySelect, 'Select a city');

    if (!selectedState) {
        return;
    }

    citySelect.disabled = false;
    selectedState.cities.forEach((city) => {
        const option = document.createElement('option');
        option.value = city;
        option.textContent = city;
        citySelect.appendChild(option);
    });
}

async function loadLocations() {
    try {
        locationData = await fetchJson('data/locations.json');
        populateCountryDropdown();
    } catch (error) {
        countrySelect.innerHTML = '<option value="">Could not load countries</option>';
    }
}

if (countrySelect) {
    countrySelect.addEventListener('change', (event) => {
        populateStateDropdown(event.target.value);
    });
}

if (stateSelect) {
    stateSelect.addEventListener('change', (event) => {
        populateCityDropdown(event.target.value);
    });
}

loadLocations();
