import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import Downshift from 'downshift';
import { withStyles } from '@material-ui/core/styles';
import TextField from '@material-ui/core/TextField';
import Paper from '@material-ui/core/Paper';
import MenuItem from '@material-ui/core/MenuItem';
import { fetchUtils, FormDataConsumer } from 'react-admin';
import { useForm } from 'react-final-form';
import useDebounce from '../../utils/useDebounce';

const queryString = require('query-string');

const fetchSuggestions = (input) => {
  if (!input) {
    return new Promise((resolve, reject) => resolve([]));
  }

  const apiUrl = process.env.REACT_APP_API + process.env.REACT_APP_GEOSEARCH_RESOURCE;
  const parameters = {
    q: `${input}`,
  };
  const urlWithParameters = `${apiUrl}?${queryString.stringify(parameters)}`;
  return fetchUtils
    .fetchJson(urlWithParameters)
    .then((response) => response.json)
    .catch((error) => {
      console.error(error);
      return [];
    });
};

const GeocompleteInput = (props) => {
  const { classes } = props;

  const form = useForm();

  const [input, setInput] = useState('');
  const [suggestions, setSuggestions] = useState([]);
  const debouncedInput = useDebounce(input, 500);

  const formState = form.getState();
  const errorMessage = props.validate(input);
  const errorState = formState.submitFailed && errorMessage;

  useEffect(() => {
    if (debouncedInput) {
      fetchSuggestions(debouncedInput).then((results) => {
        setSuggestions(
          results
            .filter((element) => element && element.displayLabel && element.displayLabel.length > 0)
            .slice(0, 10)
        );
      });
    } else {
      setSuggestions([]);
    }
  }, [debouncedInput]);

  const isSelected = (selectedItem, label) => (selectedItem || '').indexOf(label) > -1;

  return (
    <FormDataConsumer>
      {({ dispatch, ...rest }) => (
        <div className={classes.root}>
          <Downshift
            onInputValueChange={(inputValue, stateAndHelpers) =>
              setInput(inputValue ? inputValue.trim() : '')
            }
            onSelect={(selectedItem, stateAndHelpers) => {
              const address = suggestions.find((element) => element.displayLabel === selectedItem);
              if (address) {
                form.change('address', null);
                form.change(
                  'address.streetAddress',
                  address.streetAddress ? address.streetAddress : null
                );
                form.change('address.postalCode', address.postalCode);
                form.change('address.addressLocality', address.addressLocality);
                form.change('address.addressCountry', address.addressCountry);
                form.change('address.latitude', address.latitude);
                form.change('address.longitude', address.longitude);
                form.change('address.elevation', address.elevation);
                form.change('address.name', address.name);
                form.change('address.houseNumber', address.houseNumber);
                form.change('address.street', address.street);
                form.change('address.subLocality', address.subLocality);
                form.change('address.localAdmin', address.localAdmin);
                form.change('address.county', address.county);
                form.change('address.macroCounty', address.macroCounty);
                form.change('address.region', address.region);
                form.change('address.macroRegion', address.macroRegion);
                form.change('address.countryCode', address.countryCode);
                form.change('address.home', address.home);
                form.change('address.venue', address.venue);
              }
            }}
          >
            {({ getInputProps, getItemProps, isOpen, selectedItem, highlightedIndex }) => (
              <div className={classes.container}>
                <TextField
                  label={props.label || 'Adresse'}
                  className={classes.input}
                  variant="filled"
                  required
                  error={errorState}
                  helperText={errorState && errorMessage}
                  InputProps={{
                    ...getInputProps({
                      placeholder: 'Entrer une adresse',
                    }),
                  }}
                  fullWidth={true}
                />

                {isOpen ? (
                  <Paper className={classes.paper} square>
                    {suggestions.map((suggestion, index) => (
                      <MenuItem
                        {...getItemProps({
                          item: suggestion.displayLabel,
                        })}
                        key={suggestion.displayLabel}
                        selected={highlightedIndex === index}
                        component="div"
                        style={{
                          fontWeight: isSelected(selectedItem, suggestion.displayLabel) ? 500 : 400,
                        }}
                      >
                        {suggestion.displayLabel.join(' ')}
                      </MenuItem>
                    ))}
                  </Paper>
                ) : null}
              </div>
            )}
          </Downshift>
        </div>
      )}
    </FormDataConsumer>
  );
};

GeocompleteInput.propTypes = {
  classes: PropTypes.object.isRequired,
};

const styles = (theme) => ({
  root: {
    flexGrow: 1,
  },
  container: {
    flexGrow: 1,
    position: 'relative',
  },
  paper: {
    position: 'absolute',
    zIndex: 9999,
    marginTop: theme.spacing(1),
    left: 0,
    right: 0,
  },
  chip: {
    margin: `${theme.spacing(0.5)}px ${theme.spacing(0.25)}px`,
  },
  inputRoot: {
    flexWrap: 'wrap',
  },
  divider: {
    height: theme.spacing(2),
  },
  input: {
    //width: '50%',   // Change this to style the autocomplete component
    flexGrow: 1,
  },
});

export default withStyles(styles)(GeocompleteInput);