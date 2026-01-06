/**
 * FormUpload.js
 *
 * Renders an <input type="file"> based on the shortcode attributes.
 *
 * In your scenario, the shortcode might produce something like:
 * [mwai-form-upload
 *    id="..."
 *    label="..."
 *    name="..."
 *    accept="all-images"   <-- or "all-documents", "all", "custom"
 *    multiple="true"
 *    required="true"
 * ]
 * Or, if the user selected "custom", you'd have:
 *    accept=".png,.jpg"
 */
const FormUpload = (props) => {
  const { params } = props;
  const {
    id,
    label,
    type,
    name,
    required,
    className,
    accept = '',
    multiple,
    customAccept = '',
  } = params;

  const baseClass = `mwai-form-upload mwai-form-upload-${type}`;
  const classStr = `${baseClass}${className ? ` ${className}` : ''}`;
  const isRequired = required === true;

  /**
   * Convert accept setting to a valid string for the <input> 'accept' attribute.
   */
  const resolveAcceptValue = (acceptValue, customValue) => {
    switch (acceptValue) {
    case 'all-images':
      return 'image/*';
    case 'all-documents':
      // Adjust if you’d like more file types
      return '.pdf,.doc,.docx,.txt,.xls,.xlsx';
    case 'all':
      // Return empty to accept all file types
      return '';
    case 'custom':
      // Rely on the user-provided string
      return customValue || '';
    default:
      // If the block or shortcode passes a direct string (e.g., .png,.jpg),
      // we can just return it directly
      return acceptValue;
    }
  };

  const finalAccept = resolveAcceptValue(accept, customAccept);

  return (
    <fieldset
      className={classStr}
      data-form-name={name}
      data-form-type="input"
      data-form-required={isRequired}
    >
      <legend>{label}</legend>
      <div className="mwai-form-upload-container">
        {/* <label htmlFor={id}>{label}</label> */}
        <input
          id={id}
          type="file"
          name={name}
          required={isRequired}
          accept={finalAccept}
          multiple={multiple}
        />
      </div>
    </fieldset>
  );
};

export default FormUpload;
