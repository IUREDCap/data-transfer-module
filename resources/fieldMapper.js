//-------------------------------------------------------
// Copyright (C) 2025 The Trustees of Indiana University
// SPDX-License-Identifier: BSD-3-Clause
//-------------------------------------------------------

//-------------------------------------------------------
// Field mapper for mapping fields from source project
// to destination project.
//-------------------------------------------------------

if (typeof DataTransferModule === 'undefined') {
    var DataTransferModule = {};
}

// Data for variables used in query conditions, e.g., username.
DataTransferModule.variableData = [];

DataTransferModule.sourceProject = [];
DataTransferModule.destinationProject = [];

DataTransferModule.fieldMappingStatusForm = '';

/**
 * Creates a field mapper and puts it in the specified container div. The field mapper is used to specify the
 * mapping of source project fields to destination project fields for a data transfer from a source REDCap
 * project to a destination REDCap project.
 *
 * @param {number} projectId - the REDCap project ID for the project containing the data transfer configuration
 *     that contains the field map represented by this field mapper.
 *
 * @param {string} configName - the name of the data transfer configuration containing the field map.
 *
 * @param {string} redcapCsrfToken - a token generated for REDCap to include in forms to prevent crsoss-site request forgery
 *     security vulnerabilities.
 *
 * @param {HTMLElement} containerDiv - the div that will be used to contain the field mapper.
 *
 * @param {string} sourceProjectJson - a JSON string that represents the source REDCap project of the data transfer.
 *
 * @param {string} destinationProjectJson - a JSON string that represents the destination REDCap project of the data transfer.
 *
 * @param {string} fieldMapJson - a JSON string that represents the initial state of the field mapper.
 */
DataTransferModule.createFieldMapper = function(
    projectId,
    configName,
    fieldMappingStatusUrl,
    redcapCsrfToken,
    containerDiv,
    sourceProjectJson,
    destinationProjectJson,
    fieldMapJson = null
) {
    this.projectId = projectId;
    this.configName = configName;

    this.fieldMappingStatusUrl = fieldMappingStatusUrl;

    this.fieldMappingStatusForm = 
        '<form method="POST" action="' + fieldMappingStatusUrl + '" target="_blank"' + '>' + "\n"
        + '<input type="hidden" name="redcap_csrf_token" value="' + redcapCsrfToken + '"/>' + "\n"
        + '</form>';

    this.sourceProject      = DataTransferModule.createProject( sourceProjectJson );
    this.destinationProject = DataTransferModule.createProject( destinationProjectJson );

    this.container = containerDiv;

    let html = DataTransferModule.getHeaderHtml();

    this.container.html(html);

    if (fieldMapJson !== null && fieldMapJson.trim() !== '') {
        // alert(fieldMapJson);
        let fieldMappings = JSON.parse(fieldMapJson);

        for (const fieldMapping of fieldMappings) {
            // alert($(fieldMapping).html());
            this.addNewRow(fieldMapping);
        }
    }

    return false;
}

/**
 * Gets the header HTML for the field mapper.
 */
DataTransferModule.getHeaderHtml = function() {
    var html = "<div>"
        + "<div>"
        + '<button class="dtmAddFieldMapping" title="Add variable">'
        + '<i class="fa fa-circle-plus" style="color: green;"></i>'
        + ' Add field mapping'
        + '</button>'
        + '</div>'
        + '<div style="clear: both; margin-bottom: 12px;"></div>'
        + '<table id="dtm-field-map-table" class="dataTable">'
        + '<thead>'
        + '<tr>'
        + '<th rowspan="2">&nbsp;</th>'
        + '<th colspan="3">Source Project Fields</th>'
        + '<th rowspan=2">&nbsp;</th>'
        + '<th colspan="4">Destination Project Fields</th>'
        + '<th rowspan="2">Status</th>'
        + '<th rowspan="2">Add</th>'
        + '<th rowspan="2">Delete</th>'
        + '</tr>'
        + '<tr>'
        + '<th>Event</th> <th>Form</th> <th>Field</th>'
        + '<th>Event</th> <th>Form</th> <th>Field</th>'
        + '<th>Exclude</th>'
        + '</tr>'
        + '</thead>'
        + '<tbody id="dtm-field-map-table-body">'
        + '</tbody>'
        + '</table>'
        + "</div>"
        ;

    return html;
}


/**
 * Renders the HTML for a REDCap field specification.
 *
 * @param {Project} project REDCap project object for which the field specification is being generated.
 * @param {boolean} isSource Indicates if the project is the source project for the data transfer.
 * @param {string} event Event specification for the field.
 * @param {string} form Form specification for the field.
 * @param {string} field Field name for the field.
 *
 */
DataTransferModule.renderFieldHtml = function(project, isSource = true, event = null, form = null, field = null) {
    let html = "";

    let isLongitudinal = project.isLongitudinal();

    //------------------------------------------------
    // Events
    //------------------------------------------------
    if (isSource) {
        html += '<td> <select class="sourceEventSelect"';
    }
    else {
        html += '<td> <select class="destinationEventSelect"';
    }

    if (!isLongitudinal) {
        html += ' hidden';
    }

    html += ">\n";
    let uniqueEventNames = project.getUniqueEventNameOptions(isSource);

    html += DataTransferModule.renderOptionsHtml(uniqueEventNames, uniqueEventNames, event);
    html += '</select>';
    html += '</td>';


    //------------------------------------------------
    // Forms
    //------------------------------------------------
    if (isSource) {
        html += '<td> <select class="sourceFormSelect">';
    }
    else {
        html += '<td> <select class="destinationFormSelect">';
    }

    let nullMapping = this.getNullMapping();
    let formNames = project.getFormNameOptions(isSource, nullMapping);
    html += DataTransferModule.renderOptionsHtml(formNames, formNames, form);
    html += '</select>';
    html += '</td>';

    //------------------------------------------------
    // Fields
    //------------------------------------------------
    if (isSource) {
        html += '<td> <select class="sourceFieldSelect">';
    }
    else {
        html += '<td> <select class="destinationFieldSelect">';
    }

    let fieldNames = project.getFieldNameOptions(isSource, nullMapping);

    html += DataTransferModule.renderOptionsHtml(fieldNames, fieldNames, field);
    html += "</select>\n";
    html += "</td>\n";


    return html;
}

/**
 * Get a mapping object from a tr element.
 */
DataTransferModule.getMapping = function(tr) {
    let mapping = new Object();

    let tds = $(tr).find('td');

    mapping.sourceEventSelect = $(tds[1]).find('select');
    mapping.sourceEvent       = mapping.sourceEventSelect.val();

    mapping.sourceFormSelect = $(tds[2]).find('select');
    mapping.sourceForm       = mapping.sourceFormSelect.val();

    mapping.sourceFieldSelect = $(tds[3]).find('select');
    mapping.sourceField       = mapping.sourceFieldSelect.val();

    mapping.transferTd = $(tds[4]);

    mapping.destinationEventSelect = $(tds[5]).find('select');
    mapping.destinationEvent       = mapping.destinationEventSelect.val();

    mapping.destinationFormSelect = $(tds[6]).find('select');
    mapping.destinationForm       = mapping.destinationFormSelect.val();

    mapping.destinationFieldSelect = $(tds[7]).find('select');
    mapping.destinationField       = mapping.destinationFieldSelect.val();

    mapping.excludeDestinationInput = $(tds[8]).find('input');
    mapping.excludeDestination      = mapping.excludeDestinationInput.is(":checked");

    return mapping;
}

DataTransferModule.getNullMapping = function() {
    let mapping = new Object();

    mapping.sourceEventSelect = null;
    mapping.sourceEvent       = null;

    mapping.sourceFormSelect = null;
    mapping.sourceForm       = null;

    mapping.sourceFieldSelect = null;
    mapping.sourceField       = null;

    mapping.transferTd = null;

    mapping.destinationEventSelect = null;
    mapping.destinationEvent       = null;

    mapping.destinationFormSelect = null;
    mapping.destinationForm       = null;

    mapping.destinationFieldSelect = null;
    mapping.destinationField       = null;

    mapping.excudeDestiationInput = null;
    mapping.excudeDestiation      = null;

    return mapping;
}

/**
 * Renders html for select options.
 *
 * @param {array} values Option values.
 * @param {array} labels Option labels.
 * @param {string} selectedValue Selected option value.
 */
DataTransferModule.renderOptionsHtml = function(values, labels, selectedValue = null) {
    let html = "";

    for (let i = 0; i < values.length; i++) {
        let selected = '';
        if (selectedValue !== null && selectedValue === values[i]) {
            selected = ' selected';
        }

        html += '<option value="' + values[i] + '"' + selected + '>' + labels[i] + "</option>\n";
    }

    return html;
}

/**
 * Gets the JSON string representation of a table row (tr), which represents one field mapping.
 */
DataTransferModule.trToJson = function(tr) {
    let json = '';

    let mapping = DataTransferModule.getMapping(tr);
    // let tds = $(tr).find('td');
    
    json += '{';

    //-------------------------------------
    // Source Field
    //-------------------------------------

    // Add source event
    // let sourceEventSelect = $(tds[1]).find('select');
    // let sourceEvent = sourceEventSelect.val();
    // json += '"sourceEvent": "' + sourceEvent + '"';
    json += '"sourceEvent": "' + mapping.sourceEvent + '"';

    // Add source form
    // let sourceFormSelect = $(tds[2]).find('select');
    // let sourceForm = sourceFormSelect.val();
    // json += ',"sourceForm": "' + sourceForm + '"';
    json += ',"sourceForm": "' + mapping.sourceForm + '"';

    // Add source field
    // let sourceFieldSelect = $(tds[3]).find('select');
    // let sourceField = sourceFieldSelect.val();
    // json += ',"sourceField": "' + sourceField + '"';
    json += ',"sourceField": "' + mapping.sourceField + '"';

    //-------------------------------------
    // Destination Field
    //-------------------------------------

    // Add destination event
    // let destinationEventSelect = $(tds[5]).find('select');
    // let destinationEvent = destinationEventSelect.val();
    // json += ',"destinationEvent": "' + destinationEvent + '"';
    json += ',"destinationEvent": "' + mapping.destinationEvent + '"';

    // Add destination form
    // let destinationFormSelect = $(tds[6]).find('select');
    // let destinationForm = destinationFormSelect.val();
    // json += ',"destinationForm": "' + destinationForm + '"';
    json += ',"destinationForm": "' + mapping.destinationForm + '"';

    // Add destination field
    // let destinationFieldSelect = $(tds[7]).find('select');
    // let destinationField = destinationFieldSelect.val();
    // json += ',"destinationField": "' + destinationField + '"';
    json += ',"destinationField": "' + mapping.destinationField + '"';

    // Add exclude destination
    // let excludeDestination = $(tds[8]).find('input').is(":checked");
    // json += ',"excludeDestination": ' + excludeDestination;
    json += ',"excludeDestination": ' + mapping.excludeDestination;

    json += '}';

    return json;
}

/**
 * Gets a JSON representation of the field mapper. The JSON returned is
 * an array of objects, where each object represents one field mapping
 * and has the floowing properties:
 *
 * sourceEvent, sourceForm, sourceField
 * destinationEvent, destinationForn, destinationField
 *
 * @return {string} JSON representation of the field mapper.
 */
DataTransferModule.toJson = function() {
    let json = null;
    if (this.container != null) {
        json = '';

        let tbody = this.container.find('tbody').first();

        json += '[';

        let trs = $(tbody).find('tr');

        for (i = 0; i < trs.length; i++) {
            let tr = trs[i];

            let trJson = this.trToJson(tr);

            json += trJson;

            if (i < trs.length -1) {
                // Append a comma if this is not the last item
                json += ',';
            }
        }

        json += ']';
    }
    return json;
}

/**
 * Returns formatted JSON string representation of the field mapper.
 */
DataTransferModule.toFormattedJson = function() {
    let json = this.toJson();
    let obj = JSON.parse(json);
    let string = JSON.stringify(obj, null, 4);
    return string;
}




/**
 * Creates a project object for the specified project JSON text.
 */
DataTransferModule.createProject = function(projectJson) {
    // Set object properties from JSON
    project = JSON.parse( projectJson );

    project.getRecordIdField = function() {
        return project.record_id;
    }

    project.isLongitudinal = function() {
        return project.project_info.is_longitudinal;
    }

    /**
     * Gets the unqiue event names for the repeating events of the project. If the project
     * is non-longitudinal (classic) then an empty array is returned.
     */
    project.getRepeatingEvents = function() {
        return this.repeatingEvents;
    }

    project.hasRepeatingEvent = function() {
        return (this.repeatingEvents.length > 0);
    }

    project.isRepeatingEvent = function(uniqueEventName) {
        return (jQuery.inArray(uniqueEventName, this.repeatingEvents) !== -1);
    }

    project.getEventsWithRepeatingForms = function() {
        let eventsWithRepeatingForms = [];

        jQuery.each(project.event_map, function(uniqueEventName, value) {
            if (value.repeating_forms) {
                eventsWithRepeatingForms.push(uniqueEventName);
            }
        });

        return eventsWithRepeatingForms;
    }

    /**
     * Gets the repeating forms (if any) for the specified event.
     */
    project.getEventRepeatingForms = function(uniqueEventName) {
        let repeatingForms = [];

        if (this.isLongitudinal()) {
            let repeatingForms = this.repeating_forms
            for (i = 0; i < repeatingForms.length; i++) {
                let formInfo = repeatingForms[i];
                let formEvent = formInfo.unique_event_name;
                if (uniqueEvetName === formEvent) {
                    repeatingForms.push(forminfo.form);
                }
            }
        }

        return repeatingForms;
    }


    // Returns events that have instances, because they are repeating events
    // or have repeating forms
    project.getEventsWithInstances = function() {
        let eventsWithInstances = [];

        let eventMap = project.event_map;
        if (eventMap.hasOwnProperty(event)) {
            eventInfo = eventMap.event;
            if (eventInfo.isRepeating || eventInfo.repeating_forms) {
                eventsWithInstances.push(eventInfo.unique_event_name);
            }
        }

        return eventsWithInstances;
    }


    project.getMatchingEvents = function() {
        ; // TODO
    }

    project.getMatchingForms = function() {
        // TODO
    }

    project.getNonLongitudinalRepeatingForms = function() {
        repeatingForms = [];
        if (!project.isLongitudinal()) {
            repeatingForms = project.repeating_forms;
        }

        return repeatingForms;
    }

    // Indicates if any of the specified forms are non-longitudinal repeating forms
    // (i.e., have instances).
    project.areNonLongitudinalRepeatingForms = function(forms = []) {
        let areRepeating = false;

        if (!this.isLongitudinal()) {
            // See if one of the specified forms exists in the project and is repeating
            if (forms != null && forms !== '') {
                let repeatingForms = this.getNonLongitudinalRepeatingForms();
                for (let i = 0; i < forms.length; i++) {
                    if (repeatingForms.includes(forms[i])) {
                        areRepeating = true;
                        break;
                    }
                }
            }
        }

        return areRepeating;
    }
 
    // Indicates if any of the events have instances (i.e., they are
    // repeating events or are events that have repeating forms).
    // False will always be retured for non-longitudinal projects.
    project.haveInstances = function(events = []) {
        let areRepeating = false;

        if (this.isLongitudinal()) {
            if (events !== null && events !== '') {
                let repeatingEvents = this.getEventsWithInstances();
                for (let i = 0; i < events.length; i++) {
                    if (repeatingEvents.includes(events[i])) {
                        areRepeating = true;
                        break;
                    }
                }
            }
        }

        return areRepeating;
    }
 

    // TODO (Add form complete fields ????)
    // TODO if MATCHING, and source form specified, then should be restricted by same form name
    // TODO if event is MATCHING, and source event was specified, then should be restricted based
    // on possible forms for matching events

    /**
     * Gets the field names for the project for the specified event and/or form (if any).
     */
    project.getFieldNames = function(event = null, form = null, sourceEvent = null, sourceForm = null) {
        let forms = [];

        let fields = project.fields;
        // let fieldNames = [];
        let fieldNames = [];
        // let allFieldNames = Object.keys(fields);

        //-----------------------------------------------------
        // Set the possible forms
        //-----------------------------------------------------
        if (form === 'MATCHING' && sourceForm !== null && sourceForm !== '' && sourceForm !== 'ALL') {
            // destination form specified as matching, and source form is a single form
            forms.push(sourceForm);
        }
        else if (form !== null && form !== '' && form !== 'ALL' && form !== 'MATCHING') {
            // form specified as a single form
            forms.push(form);
        }
        else if (event === 'MATCHING' && sourceEvent !== null && sourceEvent !== '' && sourceEvent !== 'ALL') {
            // destination event specified as matching, and source event is a single event
            forms = project.getFormNames(sourceEvent);
        }
        else if (event !== null && event !== '' && event !== 'ALL' && event !== 'MATCHING') {
            // event specified as a single event (and no form specified)
            forms = project.getFormNames(event);
        }
        else {
            // No form or event specified
            forms = project.getFormNames();
        }


        for (let i = 0; i < forms.length; i++) {
            formFieldNames = Array.from( project.form_fields[forms[i]] );
            if (formFieldNames !== null && formFieldNames.length > 0) {
                // If the form exists in the project and has fields,
                // then add the complete field to the form fields
                // and then add the forms fields to the fields to return
                formFieldNames.push(forms[i] + '_complete');
                fieldNames.push(...formFieldNames);
            }
        }

        // fieldNames.sort();

        return fieldNames;
    }

    project.getFieldNameOptions = function(isSource = true, mapping = null) {
        let options = [];

        if (isSource) {
            // Source
            options = this.getFieldNames(mapping.sourceEvent, mapping.sourceForm, mapping.sourceEvent, mapping.sourceForm);

            // Remove record ID field, because this is handled by record matching
            options = options.filter(element => element !== this.record_id);
            options.unshift("ALL");
        }
        else {
            // Destination

            if (mapping.excludeDestination) {
                options = this.getFieldNames(mapping.destinationEvent, mapping.destinationForm, mapping.sourceEvent, mapping.sourceForm);
                options = options.filter(element => element !== this.record_id);
                options.unshift("ALL");
            }
            else {
                if (mapping.sourceField !== "ALL") {
                    options = this.getFieldNames(mapping.destinationEvent, mapping.destinationForm, mapping.sourceEvent, mapping.sourceForm);
                    options = options.filter(element => element !== this.record_id);
                }

                options.unshift("COMPATIBLE");
                options.unshift("EQUIVALENT");
            }
        }

        options.unshift("");

        return options;
    }


    // Gets the form/instrument names for the project
    project.getFormNames = function(event = null, field = null) {
        let formNames = [];

        if (field !== null && field !== '' && field !== 'ALL' && field !== 'EQUIVALENT' && field !== 'COMPATIBLE') {
            formNames.push(project.fields[field].form_name);
        }
        else if (event !== null && event !== '' && event !== 'ALL' && event !== 'MATCHING') {
            // event is set, but field is not set
            formNames = Array.from( project.event_forms[event] );
        }
        else {
            // event and field not set; return all form names
            formNames = Array.from( project.forms );
        }

        formNames.sort();

        return formNames;
    }

    project.getFormNameOptions = function(isSource, mapping) {
        let options = [];

        if (isSource) {
            options = this.getFormNames(mapping.sourceEvent, mapping.sourceField);
            // source
            if (mapping.sourceField === null || mapping.sourceField === '' || mapping.sourceField === 'ALL') {
                // If the field is not specified, include the special options
                // (if the field is specified, then the form is determined)
                options.unshift("ALL");
            }
        }
        else {
            // destination
            if (mapping.excludeDestination) {
                options = this.getFormNames(mapping.destinationEvent, mapping.destinationField);
                options.unshift("ALL");
            }
            else {
                if (mapping.sourceForm !== "ALL") {
                    options = this.getFormNames(mapping.destinationEvent, mapping.destinationField);
                }

                options.unshift("MATCHING");
            }
        }

        options.unshift("");

        return options;
    }

    // Gets the event names for the project, or for
    // the specified field in the project if it is not null
    project.getUniqueEventNames = function(form = null, field = null) {
        let uniqueEventNames = [];

        // console.log("field = " + field);
        if (project.project_info.is_longitudinal) {
            if (field !== null && field !== '' && field !== 'ALL' && field !== 'EQUIVALENT' && field !=='COMPATIBLE') {
                let fields = project.fields;
                let fieldNames = Object.keys(fields);
                // console.log("fields = " + fields);
                for (let i = 0; i < fieldNames.length; i++) {
                    let fieldName = fieldNames[i];
                    // console.log("fields[" + i + "] = " + fields[i]);
                    // console.log("fields[" + i + "],field_name = " + fields[i].field_name);
                    //if (fields[i].field_name === field) {
                    if (fieldName === field) {
                        uniqueEventNames = Array.from( fields[fieldName].events );
                        break;
                    }
                }
            }
            else if (form !== null && form !== '' && form !== 'ALL' && form !== 'MATCHING') {
                uniqueEventNames = Array.from( project.form_events[form] );
            }
            else {
                // form and field not set
                let events = project.events;
                for (let i = 0; i < events.length; i++) {
                    let event = events[i];
                    uniqueEventNames.push(event.unique_event_name);
                }
            }
        }

        uniqueEventNames.sort();

        return uniqueEventNames;
    }

    project.getUniqueEventNameOptions = function(isSource = true, sourceEventValue = '', form = null, field = null, excludeDestination = false) {
        let options = [];

        if (isSource) {
            // source event
            options = this.getUniqueEventNames(form, field);
            options.unshift("ALL");
        }
        else {
            // destination event
            if (sourceEventValue !== "ALL") {
                // If the source event is "ALL", only allow "MATCHING" and "blank/unset" as the
                // destination event options.
                options = this.getUniqueEventNames(form, field);
            }

            if (excludeDestination) {
                options.unshift("ALL");
            }
            else {
                options.unshift("MATCHING");
            }
        }

        options.unshift("");

        return options;
    }

    //----------------------------------------------------------
    // Gets the form name for the specified field
    //----------------------------------------------------------
    project.getFieldForm = function(field) {
        let form = null;

        if (field !== null && field !== '' && field !== 'ALL' && field !== 'EQUIVALENT' && field !== 'COMPATIBLE') {
            //console.log("getFieldForm field: " + field);
            //console.log("project: " + project.fields[field].form_name);
            if (field in project.fields) {
                form = project.fields[field].form_name;
            }
        }

        return form;
    }

    return project;
}

/**
 * Returns the html for a new field mapper row.
 */
DataTransferModule.renderNewRow = function(fieldMapping = null) {
    let sourceFieldHtml = '';
    let destinationFieldHtml = '';

    let excludeChecked = '';

    if (fieldMapping === null || fieldMapping === '') {
        sourceFieldHtml = this.renderFieldHtml(DataTransferModule.sourceProject, true);
        destinationFieldHtml = this.renderFieldHtml(DataTransferModule.destinationProject, false);
    }
    else {
        sourceFieldHtml = this.renderFieldHtml(
            DataTransferModule.sourceProject,
            true,
            fieldMapping.sourceEvent,
            fieldMapping.sourceForm,
            fieldMapping.sourceField
        );

        destinationFieldHtml = this.renderFieldHtml(
            DataTransferModule.destinationProject,
            false,
            fieldMapping.destinationEvent,
            fieldMapping.destinationForm,
            fieldMapping.destinationField
        );

        if (fieldMapping.excludeDestination) {
            excludeChecked = ' checked ';
        }
       
    }

    let row = "<tr>" + '<td><i class="fa fa-arrows-up-down"></i></td>"'
        // Source field speficiation
        + sourceFieldHtml

        // transfer direction arrow
        + '<td style="text-align: center; background-color: #f8f8f8;">'
        + '<i class="fa fa-arrow-right-long" style="font-size: 140%;"></i>'
        + '</td>'

        // Destination field specification
        + destinationFieldHtml
    
        // EXCLUDE
        + '<td style="text-align: center;">'
        + '<input type="checkbox" class="exclude-destination" style="font-size: 140%;"' + excludeChecked + '></input>'
        + '</td>'

        // STATUS
        + '<td style="text-align: center;">'
        + '<i class="fa fa-circle-info status-field-mapping" style="color: blue; font-size: 140%;"></i>'
        + '</td>'

        // ADD
        + '<td style="text-align: center;">'
        + '<i class="fa fa-circle-up insert-field-mapping" style="color: green; font-size: 120%;"></i>'
        + '<br/>'
        + '<i class="fa fa-circle-down append-field-mapping" style="color: green; font-size: 120%;"></i>'
        // + '<i class="fa fa-circle-down append-field-mapping" style="color: green; font-size: 140%; margin-left: 7px;"></i>'
        + '</td>'

        // DELETE
        + '<td style="text-align: center;">'
        + '<i class="fa fa-circle-minus delete-field-mapping" style="color: red; font-size: 140%;"></i>'
        + '</td>'

        + "</tr>";

    return row;
}

/**
 * Adds a new row to the field mapper.
 */
DataTransferModule.addNewRow = function(fieldMapping) {
    let row = DataTransferModule.renderNewRow(fieldMapping);

    $("#dtm-field-map-table tbody").append(row);
    $("#dtm-field-map-table-body").sortable({cursor: 'row-resize'});

    //--------------------------------------------------------
    // Set the field mapping row's selects
    //--------------------------------------------------------
    if (fieldMapping !== null && fieldMapping !== '') {
        // Get the row that was just appended to the field map table
        let addedRow = $("#dtm-field-map-table-body tr:last");

        let mapping = this.getMapping(addedRow);

        this.updateForSourceEventChange(mapping.sourceEventSelect);
        this.updateForSourceFormChange(mapping.sourceFormSelect);
        this.updateForSourceFieldChange(mapping.sourceFieldSelect);

        this.updateForDestinationEventChange(mapping.destinationEventSelect);
        this.updateForDestinationFormChange(mapping.destinationFormSelect);
        this.updateForDestinationFieldChange(mapping.destinationFieldSelect);

        this.updateForExcludeDestinationChange(mapping.excludeDestinationInput);
    }
}


DataTransferModule.checkFieldMapping = function(fieldMappingTr) {
    let status = '';

    let mapping = this.getMapping(fieldMappingTr);

    if (mapping.sourceForm === null || mapping.sourceForm === '') {
        status += "No source form specified.\n";
    }

    // TODO

    return status;
}

/**
 * Makes updates to a field mapping for a change in the specified event.
 */
DataTransferModule.updateForSourceEventChange = function(eventSelect) {
    // CSS class of eventSelect should be either "sourceEventSelect" or "destinationEventSelect"

    let eventTd = $(eventSelect).parent();  // Get the containing td element
    let tr = $(eventTd).parent();
    let mapping = DataTransferModule.getMapping(tr);

    let isSource = true;

    //---------------------------------------
    // Update source form options
    //---------------------------------------
    let formNames = DataTransferModule.sourceProject.getFormNameOptions(isSource, mapping);

    let formOptionsHtml = DataTransferModule.renderOptionsHtml(formNames, formNames, mapping.sourceForm);
    mapping.sourceFormSelect.html(formOptionsHtml);

    //---------------------------------------
    // Update source field options
    //---------------------------------------
    let fieldNames = DataTransferModule.sourceProject.getFieldNameOptions(isSource, mapping);

    let fieldOptionsHtml = DataTransferModule.renderOptionsHtml(fieldNames, fieldNames, mapping.sourceField);
    mapping.sourceFieldSelect.html(fieldOptionsHtml);

    //---------------------------------------
    // Update destination event options
    //---------------------------------------
    isSource = false;
    let uniqueEventNames = DataTransferModule.destinationProject.getUniqueEventNameOptions(
        isSource,
        mapping.sourceEvent, // always source
        mapping.destinationForm,
        mapping.destinationField,
        mapping.excludeDestination
    );

    let eventOptionsHtml = DataTransferModule.renderOptionsHtml(uniqueEventNames, uniqueEventNames, mapping.destinationEvent);
    mapping.destinationEventSelect.html(eventOptionsHtml);
}

/**
 * Makes updates to a field mapping for a change in the specified event.
 */
DataTransferModule.updateForDestinationEventChange = function(eventSelect) {
    // CSS class of eventSelect should be either "sourceEventSelect" or "destinationEventSelect"

    let eventTd = $(eventSelect).parent();  // Get the containing td element
    let tr = $(eventTd).parent();
    let mapping = DataTransferModule.getMapping(tr);

    let isSource = false;

    //--------------------------------------
    // Update destination form name options
    //--------------------------------------
    let formNames = DataTransferModule.destinationProject.getFormNameOptions(isSource, mapping);

    let formOptionsHtml = DataTransferModule.renderOptionsHtml(formNames, formNames, mapping.destinationForm);
    mapping.destinationFormSelect.html(formOptionsHtml);

    //--------------------------------------
    // Update destination field name options
    //--------------------------------------
    let fieldNames = DataTransferModule.destinationProject.getFieldNameOptions(isSource, mapping);

    let fieldOptionsHtml = DataTransferModule.renderOptionsHtml(fieldNames, fieldNames, mapping.destinationField);
    mapping.destinationFieldSelect.html(fieldOptionsHtml);
}


/**
 * Makes updates to a field mapping for a change in the specified form.
 */
DataTransferModule.updateForSourceFormChange = function(formSelect) {

    let formTd = $(formSelect).parent();  // Get the containing td element
    let tr = $(formTd).parent();

    let mapping = this.getMapping(tr);

    isSource = true;

    //----------------------------------
    // Update source event options
    //----------------------------------
    let uniqueEventNames = DataTransferModule.sourceProject.getUniqueEventNameOptions(
        isSource,
        mapping.sourceEvent, // always source
        mapping.sourceForm,
        mapping.sourceField,
        mapping.excludeDestination
    );

    let eventOptionsHtml = DataTransferModule.renderOptionsHtml(uniqueEventNames, uniqueEventNames, mapping.sourceEvent);
    mapping.sourceEventSelect.html(eventOptionsHtml);

    //----------------------------------
    // Update source field options
    //----------------------------------
    let fieldNames = DataTransferModule.sourceProject.getFieldNameOptions(isSource, mapping);

    let fieldOptionsHtml = DataTransferModule.renderOptionsHtml(fieldNames, fieldNames, mapping.sourceField);
    mapping.sourceFieldSelect.html(fieldOptionsHtml);

    //--------------------------------------
    // Update destination form options
    //--------------------------------------
    isSource = false;
    let formNames = DataTransferModule.destinationProject.getFormNameOptions(isSource, mapping);

    let formOptionsHtml = DataTransferModule.renderOptionsHtml(formNames, formNames, mapping.destinationForm);
    mapping.destinationFormSelect.html(formOptionsHtml);
}

/**
 * Makes updates to a field mapping for a change in the specified form.
 */
DataTransferModule.updateForDestinationFormChange = function(formSelect) {

    let formTd = $(formSelect).parent();  // Get the containing td element
    let tr = $(formTd).parent();

    let mapping = this.getMapping(tr);

    isSource = false;

    //--------------------------
    // Update event name options
    //--------------------------
    let uniqueEventNames = DataTransferModule.destinationProject.getUniqueEventNameOptions(
        isSource,
        mapping.sourceEvent, // always source
        mapping.destinationForm,
        mapping.destinationField,
        mapping.excludeDestination
    );

    let eventOptionsHtml = DataTransferModule.renderOptionsHtml(uniqueEventNames, uniqueEventNames, mapping.destinationEvent);
    mapping.destinationEventSelect.html(eventOptionsHtml);

    //--------------------------
    // Update field name options
    //--------------------------
    let fieldNames = DataTransferModule.destinationProject.getFieldNameOptions(isSource, mapping);

    let fieldOptionsHtml = DataTransferModule.renderOptionsHtml(fieldNames, fieldNames, mapping.destinationField);
    mapping.destinationFieldSelect.html(fieldOptionsHtml);
}

/**
 * Makes updates to a field mapping for a change in the specified field.
 */
DataTransferModule.updateForSourceFieldChange = function(fieldSelect) {
    let fieldTd = $(fieldSelect).parent();  // Get the containing td element
    let tr = $(fieldTd).parent();
    let mapping = DataTransferModule.getMapping(tr);

    let isSource = true;

    //--------------------------------------
    // Update source event name options
    //--------------------------------------
    let uniqueEventNames = DataTransferModule.sourceProject.getUniqueEventNameOptions(
        isSource,
        mapping.sourceEvent,
        mapping.sourceForm,
        mapping.sourceField,
        mapping.excludeDestination
    );

    let eventOptionsHtml = DataTransferModule.renderOptionsHtml(uniqueEventNames, uniqueEventNames, mapping.sourceEvent);
    mapping.sourceEventSelect.html(eventOptionsHtml);

    //-------------------------
    // Update form name options
    //-------------------------
    let formNames = DataTransferModule.sourceProject.getFormNameOptions(isSource, mapping);

    let formOptionsHtml = DataTransferModule.renderOptionsHtml(formNames, formNames, mapping.sourceForm);
    $(mapping.sourceFormSelect).html(formOptionsHtml);

    //--------------------------------------
    // Update destination field options
    //--------------------------------------
    isSource = false;
    let fieldNames = DataTransferModule.destinationProject.getFieldNameOptions(isSource, mapping);
    let fieldOptionsHtml = DataTransferModule.renderOptionsHtml(fieldNames, fieldNames, mapping.destinationField);
    mapping.destinationFieldSelect.html(fieldOptionsHtml);
}

/**
 * Makes updates to a field mapping for a change in the specified field.
 */
DataTransferModule.updateForDestinationFieldChange = function(fieldSelect) {
    let fieldTd = $(fieldSelect).parent();  // Get the containing td element
    let tr = $(fieldTd).parent();
    let mapping = DataTransferModule.getMapping(tr);

    let isSource = false;

    //--------------------------------------
    // Update destination event name options
    //--------------------------------------
    let uniqueEventNames = DataTransferModule.destinationProject.getUniqueEventNameOptions(
        isSource,
        mapping.sourceEvent,
        mapping.destinationForm,
        mapping.destinationField,
        mapping.excludeDestination
    );

    let eventOptionsHtml = DataTransferModule.renderOptionsHtml(uniqueEventNames, uniqueEventNames, mapping.destinationEvent);
    mapping.destinationEventSelect.html(eventOptionsHtml);

    //-------------------------
    // Update form name options
    //-------------------------
    let formNames = DataTransferModule.destinationProject.getFormNameOptions(isSource, mapping);

    let formOptionsHtml = DataTransferModule.renderOptionsHtml(formNames, formNames, mapping.destinationForm);
    $(mapping.destinationFormSelect).html(formOptionsHtml);
}

DataTransferModule.updateForExcludeDestinationChange = function(excludeDestinationInput) {
    // TODO
    let checked = $(excludeDestinationInput).is(":checked");

    let excludeTd = $(excludeDestinationInput).parent();  // Get the containing td element
    let tr = $(excludeTd).parent();

    let mapping = this.getMapping(tr);

    let tds = $(tr).find('td');

    let sourceEventSelect = $(tds[1]).find('select');
    let sourceFormSelect  = $(tds[2]).find('select');
    let sourceFieldSelect = $(tds[3]).find('select');

    let transferDirectionTd = tds[4];

    let destinationEventSelect = $(tds[5]).find('select');
    let destinationFormSelect  = $(tds[6]).find('select');
    let destinationFieldSelect = $(tds[7]).find('select');

    if (checked) {
        $(sourceEventSelect).hide();
        $(sourceFormSelect).hide();
        $(sourceFieldSelect).hide();

        $(sourceEventSelect).val("");
        $(sourceFormSelect).val("");
        $(sourceFieldSelect).val("");

        $(transferDirectionTd).html(
            '<i class="fa fa-ban" style="font-size: 140%; color: red;"></i>'
        );
    } else {
        $(sourceEventSelect).show();
        $(sourceFormSelect).show();
        $(sourceFieldSelect).show();
        $(transferDirectionTd).html(
            '<i class="fa fa-arrow-right-long" style="font-size: 140%;"></i>'
        );
    }

    let isSource = false;

    let sourceEventValue = $(sourceEventSelect).val();
    let sourceFormValue  = $(sourceFormSelect).val();
    let sourceFieldValue = $(sourceFieldSelect).val();

    let eventValue = $(destinationEventSelect).val();
    let formValue  = $(destinationFormSelect).val();
    let fieldValue = $(destinationFieldSelect).val();

    let excludeDestination = checked;

    //--------------------------
    // Update event name options
    //--------------------------
    let uniqueEventNames = DataTransferModule.destinationProject.getUniqueEventNameOptions(isSource, sourceEventValue, formValue, fieldValue, excludeDestination);

    let eventOptionsHtml = DataTransferModule.renderOptionsHtml(uniqueEventNames, uniqueEventNames, eventValue);
    destinationEventSelect.html(eventOptionsHtml);

    //-------------------------
    // Update form name options
    //-------------------------
    let formNames = DataTransferModule.destinationProject.getFormNameOptions(isSource, mapping);

    // if (fieldValue !== null && fieldValue !== '' && fieldForm !== null) {
        // formValue = fieldForm;
    // }
    let formOptionsHtml = DataTransferModule.renderOptionsHtml(formNames, formNames, formValue);
    $(destinationFormSelect).html(formOptionsHtml);

    //--------------------------
    // Update field name options
    //--------------------------
    let fieldNames = DataTransferModule.destinationProject.getFieldNameOptions(isSource, mapping);

    let fieldOptionsHtml = DataTransferModule.renderOptionsHtml(fieldNames, fieldNames, fieldValue);
    destinationFieldSelect.html(fieldOptionsHtml);
}


//--------------------------------------------------------
// INITIALIZATION AND EVENTS
//--------------------------------------------------------
$(document).ready(function(){

    // Make field map table sortable
    $("#dtm-field-map-table-body").sortable({
        cursor: 'row-resize'
    });
       
    //-----------------------------------------
    // ADD FIELD MAPPING
    //-----------------------------------------
    $("*").on("click", "button.dtmAddFieldMapping", function() {
        let row = DataTransferModule.renderNewRow();

        $("#dtm-field-map-table tbody").append(row);
        $("#dtm-field-map-table-body").sortable({cursor: 'row-resize'});

        return false;
    });

    $('.fieldSelect').autocomplete();

    //-------------------------------------------
    // CHANGE SOURCE EVENT SELECT
    //-------------------------------------------
    $("*").on("change", "select.sourceEventSelect", function() {
        let eventSelect = this;

        DataTransferModule.updateForSourceEventChange(eventSelect);

        // return false;
    });

    //-------------------------------------------
    // CHANGE DESTINATION EVENT SELECT
    //-------------------------------------------
    $("*").on("change", "select.destinationEventSelect", function() {
        let eventSelect = this;

        DataTransferModule.updateForDestinationEventChange(eventSelect);

        // return false;
    });

    //-------------------------------------------
    // CHANGE SOURCE FORM SELECT
    //-------------------------------------------
    $("*").on("change", "select.sourceFormSelect", function() {
        let formSelect = this;

        DataTransferModule.updateForSourceFormChange(formSelect);

        return false;
    });

    //-------------------------------------------
    // CHANGE DESTINATIO FORM SELECT
    //-------------------------------------------
    $("*").on("change", "select.destinationFormSelect", function() {
        let formSelect = this;

        DataTransferModule.updateForDestinationFormChange(formSelect);

        return false;
    });

    //-------------------------------------------
    // CHANGE SOURCE FIELD SELECT
    //-------------------------------------------
    $("*").on("change", "select.sourceFieldSelect", function() {
        let fieldSelect = this;

        DataTransferModule.updateForSourceFieldChange(fieldSelect);

        return false;
    });

    //-------------------------------------------
    // CHANGE DESTINATION FIELD SELECT
    //-------------------------------------------
    $("*").on("change", "select.destinationFieldSelect", function() {
        let fieldSelect = this;

        DataTransferModule.updateForDestinationFieldChange(fieldSelect);

        return false;
    });

    //------------------------------------------------------
    // FIELD MAPPING STATUS
    //------------------------------------------------------
    $("*").on("click", "i.status-field-mapping", function() {

        let tr = $(this).closest("tr");

        let status = DataTransferModule.checkFieldMapping(tr);

        let json = '[' + DataTransferModule.trToJson(tr) + ']';

        let form = jQuery(DataTransferModule.fieldMappingStatusForm);

        $("<input type='hidden' name='configName' value='" + DataTransferModule.configName + "'>").appendTo(form);
        $("<input type='hidden' name='fieldMapJson' value='" + json + "'>").appendTo(form);

        form.appendTo('body').submit();

        /*
        let statusDialog = $(document.createElement('div'));

        let contentHtml = '';
        contentHtml += '<h4>Status</h4>' + "\n"
            + status;
        // TODO

        statusDialog.html(contentHtml);

        statusDialog.dialog({
            width: 940,
            maxHeight: 480,
            modal: false,
            buttons: {
                Cancel: function() {$(this).dialog("destroy").remove();},
            },
            title: 'Status',
            dialogClass: 'status-field-mapping-dialog',
            close: function( event, ui ) {
                $(this).dialog("destroy").remove();
            }
        })
        ;
        */

        return false;
    });

    //------------------------------------------------------
    // CHECK/UNCHECK EXCLUDE
    //------------------------------------------------------
    $("*").on("change", "input.exclude-destination", function() {
        DataTransferModule.updateForExcludeDestinationChange(this);

        return false;
    });
  
    //------------------------------------------------------
    // INSERT FIELD MAPPING
    //------------------------------------------------------
    $("*").on("click", "i.insert-field-mapping", function() {
        let row = DataTransferModule.renderNewRow();

        let tr = $(this).closest("tr");
        $(tr).before(row);

        return false;
    });
  
    //------------------------------------------------------
    // APPEND FIELD MAPPING
    //------------------------------------------------------
    $("*").on("click", "i.append-field-mapping", function() {
        let row = DataTransferModule.renderNewRow();

        let tr = $(this).closest("tr");
        $(tr).after(row);

        return false;
    });
  
    //------------------------------------------------------
    // DELETE FIELD MAPPING
    //------------------------------------------------------
    $("*").on("click", "i.delete-field-mapping", function() {
        var tr = $(this).closest("tr");
        tr.remove();
        return false;
    });

});


