<div class="row-fluid">
	<div class="box $BoxCSSClass">
            <div class="title row-fluid">
              <h4 class="pull-left"><span>$Title</span></h4>
              
              
              <div class="btn-toolbar pull-right ">
                <div class="btn-group"> <a class="btn" href="$Link(add)">Add New +</a> </div>
              </div>
             
              
            </div>
		<!-- End .title -->
		<div class="content top">
			<table cellpadding="0" cellspacing="0" border="0" class="responsive table table-striped table-bordered" id="$idSelector">
				<thead>
					<tr>
						<% loop DTColumns %>
						<th>$Label</th>
						<% end_loop %>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>
</div>