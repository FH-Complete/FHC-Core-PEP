export default {
	data() {
		return {
			activeCell: null,
			focusFields: []
		};
	},
	methods: {
		focusNext(cell, next = true)
		{
			if (!cell || this.focusFields.length === 0)
				return;

			const table = cell.getTable();

			const visibleFields = table.getColumns()
				.filter(col => col.isVisible())
				.map(col => col.getField())
				.filter(field => field);

			const filedsToFocus = this.focusFields.filter(field => visibleFields.includes(field));
			const field = cell.getField();
			const row = cell.getRow();

			const allColumns = table.getColumns();
			const editableFields = allColumns.map(col => col.getField()).filter(field => filedsToFocus.includes(field));

			let nextIndex = editableFields.indexOf(field);

			nextIndex = next ? nextIndex + 1 : nextIndex - 1;

			let nextRow = row;

			if (next)
			{
				if (nextIndex >= editableFields.length)
				{
					nextIndex = 0;
					nextRow = row.getNextRow();
					if (!nextRow)
					{
						nextRow = table.getRows()[0];
					}
				}
			}
			else
			{
				if (nextIndex < 0)
				{
					nextIndex = editableFields.length - 1;
					nextRow = row.getPrevRow();
					if (!nextRow)
					{
						let rows = table.getRows();
						nextRow = rows[rows.length - 1];
					}
				}
			}

			if (nextRow)
			{
				let nextField = editableFields[nextIndex];
				let nextCell = nextRow.getCell(nextField);
				if (nextCell)
				{
					nextCell?.getElement()?.focus();
				}
			}
		},

		addFocus(refName, fields) {

			const table = this.$refs[refName]?.tabulator;
			if (!table)
				return;

			this.focusFields = fields

			table.element.addEventListener("keydown", (event) => {
				if (!this.activeCell) return;
				const editorElement = this.activeCell.getElement().querySelector("textarea");

				if (event.key === "Tab" && event.shiftKey)
				{
					event.preventDefault();
					this.focusNext(this.activeCell, false);
				}
				else if (event.key === "Tab")
				{
					event.preventDefault();
					this.focusNext(this.activeCell);
				}
				else if (event.shiftKey && event.key === "Enter")
				{
					event.preventDefault();
					this.focusNext(this.activeCell);
				}
				else if (event.key === "Enter" && !editorElement) {
					event.preventDefault();
					this.focusNext(this.activeCell);
				}
			});

			table.on("cellEditing", (cell) => {
				this.activeCell = cell;
			});

		}
	}
}
