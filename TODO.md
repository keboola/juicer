- Simplify the flow of Job, ideally to control the flow from a single (run()?) function
	- JsonJob/JsonRecursiveJob can now be narrowed down to firstPage + nextPage

- Read appName into controllers from parameters.yml (wherever container is available? use services.yml parameter for Executor if needed)

- endpoint/{dataFrom:in.c-whatever.blah.column}/... to load config from a static table

- execute ex with a single job(+ its children)?
