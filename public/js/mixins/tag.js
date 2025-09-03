export default {
	methods: {
		addTagInTable(addedTag, refName, matchKey, responseKey, fieldOrFunction = 'tags')
		{

			const table = this.$refs[refName]?.tabulator;
			if (!table)
				return;

			let isFunction = typeof fieldOrFunction === 'function';

			table.getRows().forEach(row => {
				const rowData = row.getData();
				if (!Array.isArray(addedTag[responseKey]))
					return;

				let updated = false;

				addedTag[responseKey].forEach(tag => {
					if (rowData[matchKey] !== tag[matchKey])
						return;

					let newTag = { ...addedTag, id: tag.id};

					let field = isFunction ? fieldOrFunction(newTag) : fieldOrFunction;

					if (!rowData[field])
						return;

					let fieldData;
					try {
						fieldData = JSON.parse(rowData[field]);
					} catch (e) {
						console.warn(`Invalid JSON in field '${field}':`, rowData[field]);
						return;
					}


					if (fieldData.some(tag => tag?.id === newTag.id)) return;

					fieldData.unshift(newTag);
					rowData[field] = JSON.stringify(fieldData);
					updated = true;
				})

				if (updated)
				{
					row.update(rowData);
				}
			});

		},

		deleteTagInTable(deletedTag, refName, fields = ['tags'])
		{
			const table = this.$refs[refName]?.tabulator;
			if (!table)
				return;

			table.getRows().forEach(row => {
				const rowData = row.getData();
				let updated = false;

				fields.forEach(field => {
					let fieldData;

					try {
						fieldData = JSON.parse(rowData[field]);
					} catch (e) {
						console.warn(`Invalid JSON in field '${field}':`, rowData[field]);
						return;
					}

					let filteredTag = fieldData.filter(tag => tag?.id !== deletedTag);
					const updatedTags = JSON.stringify(filteredTag);


					if (updatedTags !== rowData[field])
					{
						rowData[field] = updatedTags;
						updated = true;
					}
				})

				if (updated)
				{
					row.update(rowData);
				}

			})
		},

		updateTagInTable(updatedTag, refName, fields = ['tags'])
		{

			const table = this.$refs[refName]?.tabulator;
			if (!table)
				return;

			table.getRows().forEach(row =>
			{
				const rowData = row.getData();
				let updated = false;

				fields.forEach(field =>
				{
					if (!rowData[field])
						return;

					let fieldData;

					try {
						fieldData = JSON.parse(rowData[field]);
					} catch (e)
					{
						console.warn(`Invalid JSON in field '${field}':`, rowData[field]);
						return;
					}

					let index = fieldData.findIndex(tag => tag?.id === updatedTag.id);

					if (index !== -1)
					{
						fieldData[index] = updatedTag;
						let updatedFieldData = JSON.stringify(fieldData);

						if (updatedFieldData !== rowData[field])
						{
							rowData[field] = updatedFieldData;
							updated = true;
						}
					}
				});

				if (updated)
				{
					row.update(rowData);
				}
			});
		},
	}
}
